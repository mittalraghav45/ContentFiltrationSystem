<?php
declare(strict_types=1);
require_once __DIR__ . '/s3-object.php';
header('Cache-Control: no-store');
function esc(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
$rows = [];
$error = null;
try {
    $s3 = storageClient();
    $bucket = getenv('AWS_BUCKET');
    $page = $s3->listObjectsV2(['Bucket' => $bucket, 'Prefix' => 'uploads/', 'MaxKeys' => 100,
        'ContinuationToken' => isset($_GET['cursor']) && is_string($_GET['cursor']) ? $_GET['cursor'] : null]);
    foreach ($page['Contents'] ?? [] as $object) {
        try {
            $tags = $s3->getObjectTagging(['Bucket' => $bucket, 'Key' => $object['Key']]);
            $status = 'pending';
            foreach ($tags['TagSet'] as $tag) {
                if ($tag['Key'] === 'moderation') { $status = $tag['Value']; }
            }
            $url = null;
            if ($status === 'approved') {
                $command = $s3->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => $object['Key']]);
                $url = (string) $s3->createPresignedRequest($command, '+5 minutes')->getUri();
            }
            $rows[] = ['key' => $object['Key'], 'size' => $object['Size'], 'status' => $status, 'url' => $url];
        } catch (\Aws\S3\Exception\S3Exception $e) {
            if ($e->getStatusCode() !== 404) { throw $e; }
        }
    }
} catch (Throwable $e) {
    error_log('Listing failed: ' . $e->getMessage());
    http_response_code(503);
    $error = 'Cannot load files. Check AWS configuration, installed dependencies and server logs.';
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Moderation status</title><link rel="stylesheet" href="css/style.css"></head>
<body><div class="page"><header class="topbar"><div class="brand">Content Filtration</div>
<nav class="actions"><a class="btn ghost" href="index.php">Upload</a><a class="btn ghost" href="view_data.php">Refresh</a></nav></header>
<h1>Image moderation status</h1><p class="lede">Refresh to check progress. Only approved images can be opened. Flagged images are moved to the private quarantine bucket.</p>
<section class="card table-card">
<?php if ($error): ?><p role="alert"><?= esc($error) ?></p><?php else: ?>
<div class="table-wrapper"><table><thead><tr><th>File</th><th>Size</th><th>Status</th><th>View</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td class="mono"><?= esc($row['key']) ?></td><td><?= number_format($row['size']) ?> bytes</td><td><?= esc($row['status']) ?></td>
<td><?php if ($row['url']): ?><a class="link" target="_blank" rel="noopener noreferrer" href="<?= esc($row['url']) ?>">Open</a><?php else: ?>Awaiting approval<?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="4" class="empty">No images in the upload bucket.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if (!empty($page['NextContinuationToken'])): ?><a class="btn" href="?cursor=<?= esc(rawurlencode($page['NextContinuationToken'])) ?>">Next page</a><?php endif; ?>
<?php endif; ?></section></div></body></html>
