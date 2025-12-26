<?php
error_reporting(0);
require_once("s3-object.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>View Data</title>
    <link rel="stylesheet" href="dropzone/dist/dropzone.css" />
    <link rel="stylesheet" href="css/style.css" />
</head>

<body>
    <div class="page">
        <header class="topbar">
            <div class="brand">Content Filtration</div>
            <nav class="actions">
                <a class="btn ghost" href="index.php">Upload</a>
            </nav>
        </header>

        <main class="layout">
            <section class="intro">
                <p class="eyebrow">Live bucket contents</p>
                <h1>Files in uploadfilesforverification</h1>
                <p class="lede">Every upload shown here is moderated by Lambda; flagged content moves to the flagged bucket.</p>
            </section>

            <section class="card table-card">
                <div class="table-header">
                    <div class="table-title">Objects</div>
                    <div class="table-meta">Bucket: <?php echo htmlspecialchars($bucketName); ?></div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Size</th>
                                <th>View</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $objects = $s3->getIterator('ListObjects', array("Bucket" => $bucketName));
                            $hasObjects = false;
                            foreach ($objects as $object) {
                                $hasObjects = true;
                                $file_name = $object['Key'];
                                $file_size = $object['Size'];
                                $object_url = $s3->getObjectUrl($bucketName, $file_name);
                            ?>
                                <tr>
                                    <td class="mono"><?php echo htmlspecialchars($file_name); ?></td>
                                    <td><?php echo number_format($file_size); ?> bytes</td>
                                    <td><a class="link" target="_blank" href="<?php echo htmlspecialchars($object_url); ?>">Open</a></td>
                                </tr>
                            <?php
                            }
                            if (!$hasObjects) {
                            ?>
                                <tr>
                                    <td colspan="3" class="empty">No files found. Upload something to see it here.</td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</body>

</html>
