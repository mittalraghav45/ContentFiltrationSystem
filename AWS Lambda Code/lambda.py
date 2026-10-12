"""S3 ObjectCreated handler. Deploy this file with handler lambda.lambda_handler."""
import os
from urllib.parse import unquote_plus

import boto3


def lambda_handler(event, context):
    source = os.environ['SOURCE_BUCKET']
    flagged = os.environ['FLAGGED_BUCKET']
    if source == flagged:
        raise ValueError('Source and flagged buckets must be different')
    confidence = float(os.environ.get('MIN_CONFIDENCE', '90'))
    if not 0 <= confidence <= 100:
        raise ValueError('MIN_CONFIDENCE must be between 0 and 100')
    s3 = boto3.client('s3')
    rekognition = boto3.client('rekognition')
    results, failures = [], []
    for record in event.get('Records', []):
        if not record.get('eventName', '').startswith('ObjectCreated:'):
            continue
        bucket = record['s3']['bucket']['name']
        key = unquote_plus(record['s3']['object']['key'])
        if bucket != source or not key.startswith('uploads/'):
            continue
        try:
            try:
                metadata = s3.head_object(Bucket=bucket, Key=key)
            except Exception as exc:
                # A retried notification may refer to an already quarantined object.
                if getattr(exc, 'response', {}).get('Error', {}).get('Code') in ('404', 'NoSuchKey', 'NotFound'):
                    results.append({'key': key, 'status': 'already_removed'})
                    continue
                raise
            if metadata.get('ContentType') not in ('image/jpeg', 'image/png'):
                status = 'unsupported'
            else:
                response = rekognition.detect_moderation_labels(
                    Image={'S3Object': {'Bucket': bucket, 'Name': key}},
                    MinConfidence=confidence,
                )
                status = 'flagged' if response.get('ModerationLabels') else 'approved'
            if status in ('flagged', 'unsupported'):
                s3.copy_object(Bucket=flagged, Key=key,
                               CopySource={'Bucket': bucket, 'Key': key},
                               TaggingDirective='REPLACE', Tagging='moderation=' + status)
                # Delete only after the quarantine copy succeeds.
                s3.delete_object(Bucket=bucket, Key=key)
            else:
                s3.put_object_tagging(Bucket=bucket, Key=key,
                    Tagging={'TagSet': [{'Key': 'moderation', 'Value': 'approved'}]})
            results.append({'key': key, 'status': status})
        except Exception as exc:
            print(f'Moderation failed for {key}: {type(exc).__name__}')
            failures.append(key)
    if failures:
        # Allow Lambda asynchronous retry; do not acknowledge partially failed batches.
        raise RuntimeError(f'Moderation failed for {len(failures)} object(s)')
    return {'results': results}
