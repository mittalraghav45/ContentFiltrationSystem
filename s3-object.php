<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

// AWS Credentials (read from environment; never hard-code secrets)
$bucketName = getenv('AWS_BUCKET') ?: 'uploadfilesforverification';
$IAM_KEY = getenv('AWS_ACCESS_KEY_ID') ?: '';
$IAM_SECRET = getenv('AWS_SECRET_ACCESS_KEY') ?: '';

try {
    $s3 = S3Client::factory(
        array(
            'credentials' => array(
                'key' => $IAM_KEY,
                'secret' => $IAM_SECRET,
                'ACL'    => 'public-read'
            ),
            'version' => 'latest',
            'region'  => 'eu-north-1'
        )
    );
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
