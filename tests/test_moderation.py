import importlib
import os
import sys
import unittest
from unittest.mock import MagicMock, patch

# AWS clients are mocked: these tests never call AWS or require credentials.
sys.modules.setdefault('boto3', MagicMock())
moderation = importlib.import_module('lambda')


def record(key='uploads/a.jpg', bucket='source'):
    return {'eventName': 'ObjectCreated:Put', 's3': {'bucket': {'name': bucket}, 'object': {'key': key}}}


class ModerationTests(unittest.TestCase):
    def setUp(self):
        self.env = patch.dict(os.environ, {'SOURCE_BUCKET': 'source', 'FLAGGED_BUCKET': 'quarantine', 'MIN_CONFIDENCE': '90'})
        self.env.start()
        self.addCleanup(self.env.stop)
        self.s3, self.rek = MagicMock(), MagicMock()
        self.s3.head_object.return_value = {'ContentType': 'image/jpeg'}
        self.rek.detect_moderation_labels.return_value = {'ModerationLabels': []}
        self.client = patch.object(moderation.boto3, 'client', side_effect=lambda name: self.s3 if name == 's3' else self.rek)
        self.client.start()
        self.addCleanup(self.client.stop)

    def run_event(self, *records):
        return moderation.lambda_handler({'Records': list(records)}, None)

    def test_batch_and_encoded_keys(self):
        self.run_event(record('uploads/a+b%2B.jpg'), record('uploads/b.jpg'))
        self.assertEqual(self.rek.detect_moderation_labels.call_count, 2)
        self.assertEqual(self.rek.detect_moderation_labels.call_args_list[0].kwargs['Image']['S3Object']['Name'], 'uploads/a b+.jpg')
        self.assertEqual(self.s3.put_object_tagging.call_count, 2)
        self.s3.delete_object.assert_not_called()

    def test_flagged_is_copied_before_deleted(self):
        self.rek.detect_moderation_labels.return_value = {'ModerationLabels': [{'Name': 'Explicit'}]}
        self.assertEqual(self.run_event(record())['results'][0]['status'], 'flagged')
        names = [call[0] for call in self.s3.mock_calls]
        self.assertLess(names.index('copy_object'), names.index('delete_object'))
        self.s3.put_object_tagging.assert_not_called()

    def test_copy_failure_never_deletes_and_continues_batch(self):
        self.rek.detect_moderation_labels.return_value = {'ModerationLabels': [{'Name': 'Explicit'}]}
        self.s3.copy_object.side_effect = RuntimeError('Access denied')
        with self.assertRaises(RuntimeError):
            self.run_event(record(), record('uploads/b.jpg'))
        self.s3.delete_object.assert_not_called()
        self.assertEqual(self.s3.copy_object.call_count, 2)

    def test_rekognition_failure_stays_unapproved(self):
        self.rek.detect_moderation_labels.side_effect = RuntimeError('Unavailable')
        with self.assertRaises(RuntimeError):
            self.run_event(record())
        self.s3.put_object_tagging.assert_not_called()
        self.s3.delete_object.assert_not_called()

    def test_removed_object_retry(self):
        exc = RuntimeError('Missing')
        exc.response = {'Error': {'Code': '404'}}
        self.s3.head_object.side_effect = exc
        self.assertEqual(self.run_event(record())['results'][0]['status'], 'already_removed')
        self.rek.detect_moderation_labels.assert_not_called()

    def test_unsupported_quarantined(self):
        self.s3.head_object.return_value = {'ContentType': 'application/pdf'}
        self.assertEqual(self.run_event(record())['results'][0]['status'], 'unsupported')
        self.rek.detect_moderation_labels.assert_not_called()

    def test_other_buckets_and_prefixes_ignored(self):
        self.run_event(record(bucket='quarantine'), record('other/a.jpg'))
        self.s3.head_object.assert_not_called()

    def test_same_bucket_rejected(self):
        os.environ['FLAGGED_BUCKET'] = 'source'
        with self.assertRaises(ValueError):
            self.run_event(record())

    def test_invalid_confidence_rejected(self):
        os.environ['MIN_CONFIDENCE'] = '101'
        with self.assertRaises(ValueError):
            self.run_event(record())

    def test_missing_records(self):
        self.assertEqual(self.run_event(), {'results': []})


if __name__ == '__main__':
    unittest.main()
