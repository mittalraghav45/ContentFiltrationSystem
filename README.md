# Content Filtration System

A PHP image uploader backed by private Amazon S3 buckets and an asynchronous Python Lambda using Amazon Rekognition. Valid JPEG/PNG images are uploaded with a pending tag. Lambda approves images with no moderation labels at the configured confidence threshold, or copies flagged images to quarantine before deleting the source. The gallery exposes short-lived links only for approved images.

## Requirements

- PHP 8.1+ with fileinfo, SimpleXML and the AWS SDK dependencies; Composer 2.
- AWS credentials with the permissions below, two private S3 buckets, and a Python 3.12 Lambda.
- Use the same AWS region for both buckets, Lambda and Rekognition, choosing a region that supports Rekognition.

## Run locally

```sh
composer install
export AWS_REGION=eu-west-1
export AWS_BUCKET=your-private-upload-bucket
export AWS_PROFILE=default
php -d upload_max_filesize=5M -d post_max_size=6M -S 127.0.0.1:8000
```

On Windows PowerShell, set environment variables with `$env:AWS_REGION="eu-west-1"`, `$env:AWS_BUCKET="your-private-upload-bucket"` and `$env:AWS_PROFILE="default"`, then run the same PHP command. Configure your AWS profile first, or use `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` and, for temporary credentials, `AWS_SESSION_TOKEN`. Never commit credentials. `.env.example` documents settings; PHP does **not** automatically load `.env` files.

Open http://127.0.0.1:8000. Upload a JPEG or PNG (maximum 5 MiB), then click **View Files** and **Refresh** to see the result. An upload success means **pending**, not approved. Flagged images disappear from the gallery and are retained in the quarantine bucket for review.

This repository has no user authentication. The built-in server is for local use. Before exposing it to other users, add authentication/authorisation, CSRF protection, request limits and a production web server with a restricted document root. Never serve configuration or repository files publicly. AWS services can incur charges.

## Configure AWS moderation

1. Create two distinct buckets; keep **Block Public Access enabled** and ACLs disabled (Bucket owner enforced). Use a fresh upload bucket or migrate legacy public objects before use. The app never sets a public ACL.
2. Give the application's IAM identity `s3:ListBucket` on the upload bucket and `s3:PutObject`, `s3:PutObjectTagging`, `s3:GetObject`, `s3:GetObjectTagging` on its `uploads/*` objects. Presigned URLs use that identity's permissions.
3. Create a Python 3.12 Lambda. Upload the root `lambda.py` as a ZIP containing that file at the root, and set the handler to `lambda.lambda_handler`. Boto3 is available in the AWS Python runtime. The copy under `AWS Lambda Code/` is kept identical for compatibility.
4. Set Lambda environment variables `SOURCE_BUCKET`, `FLAGGED_BUCKET` and optionally `MIN_CONFIDENCE` (default 90, range 0–100).
5. Give Lambda CloudWatch logging permissions, `rekognition:DetectModerationLabels`, source `s3:GetObject`, `s3:PutObjectTagging`, `s3:DeleteObject`, and quarantine `s3:PutObject` / `s3:PutObjectTagging`. Add source `s3:ListBucket` so missing objects return 404 for retries. Scope S3 object permissions to `uploads/*`. Customer-managed KMS encryption additionally requires suitable KMS permissions.
6. Add an S3 **All object create events** trigger on the upload bucket with prefix `uploads/`. Do not attach the trigger to quarantine. The console must grant S3 permission to invoke the function. Configure a failure destination and alarms for asynchronous failures; pending images must never be assumed safe.
7. Allow at least 60 seconds of Lambda timeout and adjust for expected batch sizes. This project assumes unique, immutable upload keys (the PHP endpoint generates random keys). Do not overwrite keys through other clients. If enabling bucket versioning, note that source deletion creates a delete marker; old versions require a retention/lifecycle policy.

## Verification

```sh
python -m unittest discover -s tests -v
php -l index.php
php -l file-upload.php
php -l view_data.php
php -l s3-object.php
```

GitHub Actions installs the Composer lock file, checks PHP syntax, runs the mocked Lambda regression suite and checks the two Lambda copies match. Tests cover batch processing, encoded keys, approvals, quarantine order, copy/scanner failures, retries and configuration errors. They do not prove a particular AWS account is configured correctly.

For an AWS smoke test, upload a benign image and confirm pending → approved, then open its five-minute signed link. Test quarantine using an appropriate moderation test image; confirm the private destination copy exists before the source disappears. A non-image upload must return 415; oversized files return 413. Stop Lambda or deny Rekognition temporarily in a test environment to confirm failed scans stay inaccessible and produce a retried invocation. Restore permissions afterwards.

## Troubleshooting

- **503 / storage unavailable:** run `composer install`; check environment variables, credentials, bucket region and IAM permissions in server logs.
- **Images remain pending:** check the S3 trigger, Lambda handler, environment, region, permissions and CloudWatch errors.
- **Upload rejected:** use a real JPEG/PNG under 5 MiB; configure PHP upload/post limits as shown above.
- **Expired link:** refresh the gallery for a fresh signed URL.

The moderation threshold is a configurable screening policy, not a guarantee of safe content. Human review may still be needed.
