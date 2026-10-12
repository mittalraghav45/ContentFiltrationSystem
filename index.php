<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Content Filter Uploads</title>
    <link rel="stylesheet" href="dropzone/dist/dropzone.css" />
    <link rel="stylesheet" href="css/style.css" />

</head>

<body>
    <div class="page">
        <header class="topbar">
            <div class="brand">Content Filtration</div>
            <nav class="actions">
                <a class="btn ghost" href="view_data.php">View Files</a>
            </nav>
        </header>

        <main class="layout">
            <section class="intro">
                <p class="eyebrow">Secure Upload &amp; Screen</p>
                <h1>Drop files to scan with AWS Rekognition</h1>
                <p class="lede">
                    We route uploads to S3, trigger a Lambda for moderation, and keep flagged files separate.
                    Upload JPEG or PNG images up to 5 MiB each. Check View Files for approval.
                </p>
                <div class="pill-row">
                    <span class="pill">Uploads → <strong>Private upload bucket</strong></span>
                    <span class="pill">Flagged → <strong>Private quarantine bucket</strong></span>
                </div>
            </section>

            <section class="card upload-card">
                <form id="image-upload" action="file-upload.php" method="post" enctype="multipart/form-data" class="dropzone upload-zone">
                    <div class="dz-message">
                        <div class="dz-hero-icon">UP</div>
                        <div class="dz-title">Drop files or click to browse</div>
                        <p class="dz-help">JPEG and PNG only, up to 5 MiB each. Upload success means pending moderation.</p>
                    </div>
                    <div class="fallback">
                        <input id="file" name="file" type="file" accept="image/jpeg,image/png" />
                    <button type="submit">Upload image</button></div>
                </form>
                <p class="hint">Tip: keep this tab open while uploads complete.</p>
            </section>
        </main>
    </div>

    <script src="dropzone/dist/dropzone.js"></script>
<script>
    Dropzone.options.imageUpload = {
        acceptedFiles: 'image/jpeg,image/png', maxFilesize: 5, timeout: 120000,
        init: function () {
            this.on('success', function (file) {
                file.previewElement.querySelector('[data-dz-name]').textContent = file.name + ' — awaiting moderation';
            });
            this.on('error', function (file, response) {
                var message = typeof response === 'object' ? response.error : response;
                file.previewElement.querySelector('[data-dz-errormessage]').textContent = message || 'Upload failed';
            });
        }
    };
    </script>
</body>

</html>
