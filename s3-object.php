<?php
declare(strict_types=1);

function storageClient(): \Aws\S3\S3Client
{
    if (!is_file(__DIR__ . '/vendor/autoload.php')) {
        throw new RuntimeException('Dependencies missing. Run composer install first.');
    }
    require_once __DIR__ . '/vendor/autoload.php';
    if (!getenv('AWS_BUCKET') || !getenv('AWS_REGION')) {
        throw new RuntimeException('Set AWS_BUCKET and AWS_REGION before starting the server.');
    }
    // Default credential chain supports environment, profiles and IAM roles/session tokens.
    return new \Aws\S3\S3Client(['version' => 'latest', 'region' => getenv('AWS_REGION')]);
}
