<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Content Filter Uploads</title>
    <link rel="stylesheet" href="dropzone/dist/dropzone.css" />
    <link rel="stylesheet" href="css/style.css" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.2/jquery.min.js"></script>
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
                    Add as many files as you need — the scan runs automatically.
                </p>
                <div class="pill-row">
                    <span class="pill">Uploads → <strong>uploadfilesforverification</strong></span>
                    <span class="pill">Flagged → <strong>uploadfilesforverification-flagged</strong></span>
                </div>
            </section>

            <section class="card upload-card">
                <form action="file-upload.php" class="dropzone upload-zone">
                    <div class="dz-message">
                        <div class="dz-hero-icon">UP</div>
                        <div class="dz-title">Drop files or click to browse</div>
                        <p class="dz-help">We accept multiple files. Each upload is inspected for unsafe content.</p>
                    </div>
                    <div class="fallback">
                        <input id="file" name="file" type="file" multiple />
                    </div>
                </form>
                <p class="hint">Tip: keep this tab open while uploads complete.</p>
            </section>
        </main>
    </div>

    <script src="dropzone/dist/dropzone.js"></script>
</body>

</html>
