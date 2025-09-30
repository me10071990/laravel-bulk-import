<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Bulk Import System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 { color: #333; margin-bottom: 10px; font-size: 32px; }
        .subtitle { color: #666; margin-bottom: 40px; font-size: 16px; }
        .section {
            margin-bottom: 40px;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 15px;
            border: 2px dashed #ddd;
        }
        .section h2 { color: #667eea; margin-bottom: 20px; font-size: 24px; }
        .upload-area {
            border: 3px dashed #667eea;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover { background: #f0f4ff; border-color: #764ba2; }
        .upload-area.dragover { background: #e8f0fe; border-color: #4285f4; }
        input[type="file"] { display: none; }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #e0e0e0;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
            display: none;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            width: 0%;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        .result { margin-top: 30px; padding: 20px; border-radius: 10px; display: none; }
        .result.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .result.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .summary-item {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .summary-item .label { color: #666; font-size: 14px; margin-bottom: 5px; }
        .summary-item .value { color: #667eea; font-size: 28px; font-weight: bold; }
        .icon { font-size: 48px; margin-bottom: 15px; }
        .file-name { margin-top: 15px; color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bulk Import System</h1>
        <p class="subtitle">Import products from CSV and upload images with chunking support</p>
        
        <div class="section">
            <h2>CSV Import</h2>
            <div class="upload-area" id="csvUploadArea">
                <div class="icon">📄</div>
                <p style="font-size: 18px; color: #333; margin-bottom: 10px;">
                    <strong>Drop CSV file here or click to browse</strong>
                </p>
                <p style="color: #666; font-size: 14px;">
                    Required columns: sku, name, price<br>Optional: description, stock
                </p>
                <input type="file" id="csvFile" accept=".csv,.txt">
            </div>
            <div class="file-name" id="csvFileName"></div>
            <div style="margin-top: 20px;">
                <button class="btn" id="importBtn" disabled>Import Products</button>
            </div>
            <div class="progress-bar" id="csvProgress">
                <div class="progress-bar-fill" id="csvProgressFill">0%</div>
            </div>
            <div class="result" id="csvResult"></div>
        </div>
        
        <div class="section">
            <h2>Chunked Image Upload</h2>
            <div class="upload-area" id="imageUploadArea">
                <div class="icon">🎨</div>
                <p style="font-size: 18px; color: #333; margin-bottom: 10px;">
                    <strong>Drop image here or click to browse</strong>
                </p>
                <p style="color: #666; font-size: 14px;">
                    Supports JPG, PNG, GIF<br>Automatically creates 256px, 512px, 1024px variants
                </p>
                <input type="file" id="imageFile" accept="image/*">
            </div>
            <div class="file-name" id="imageFileName"></div>
            <div style="margin-top: 20px;">
                <button class="btn" id="uploadBtn" disabled>Upload Image</button>
            </div>
            <div class="progress-bar" id="imageProgress">
                <div class="progress-bar-fill" id="imageProgressFill">0%</div>
            </div>
            <div class="result" id="imageResult"></div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const csvUploadArea = document.getElementById('csvUploadArea');
        const csvFile = document.getElementById('csvFile');
        const csvFileName = document.getElementById('csvFileName');
        const importBtn = document.getElementById('importBtn');
        const csvProgress = document.getElementById('csvProgress');
        const csvProgressFill = document.getElementById('csvProgressFill');
        const csvResult = document.getElementById('csvResult');
        
        csvUploadArea.addEventListener('click', () => csvFile.click());
        csvUploadArea.addEventListener('dragover', (e) => { e.preventDefault(); csvUploadArea.classList.add('dragover'); });
        csvUploadArea.addEventListener('dragleave', () => csvUploadArea.classList.remove('dragover'));
        csvUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            csvUploadArea.classList.remove('dragover');
            if (e.dataTransfer.files.length > 0) {
                csvFile.files = e.dataTransfer.files;
                handleCsvFileSelect();
            }
        });
        csvFile.addEventListener('change', handleCsvFileSelect);
        
        function handleCsvFileSelect() {
            if (csvFile.files.length > 0) {
                csvFileName.textContent = 'Selected: ' + csvFile.files[0].name;
                importBtn.disabled = false;
            }
        }
        
        importBtn.addEventListener('click', async () => {
            const file = csvFile.files[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('csv_file', file);
            importBtn.disabled = true;
            csvProgress.style.display = 'block';
            csvProgressFill.style.width = '50%';
            csvProgressFill.textContent = 'Importing...';
            csvResult.style.display = 'none';
            
            try {
                const response = await fetch('/api/products/import', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    body: formData
                });
                const data = await response.json();
                csvProgressFill.style.width = '100%';
                csvProgressFill.textContent = 'Complete!';
                if (data.success) {
                    csvResult.className = 'result success';
                    csvResult.style.display = 'block';
                    csvResult.innerHTML = `<h3>Import Completed Successfully!</h3><div class="summary">
                        <div class="summary-item"><div class="label">Total Rows</div><div class="value">${data.summary.total}</div></div>
                        <div class="summary-item"><div class="label">Imported</div><div class="value">${data.summary.imported}</div></div>
                        <div class="summary-item"><div class="label">Updated</div><div class="value">${data.summary.updated}</div></div>
                        <div class="summary-item"><div class="label">Invalid</div><div class="value">${data.summary.invalid}</div></div>
                        <div class="summary-item"><div class="label">Duplicates</div><div class="value">${data.summary.duplicates}</div></div>
                    </div>`;
                }
            } catch (error) {
                csvResult.className = 'result error';
                csvResult.style.display = 'block';
                csvResult.innerHTML = '<h3>Error</h3><p>' + error.message + '</p>';
            } finally {
                importBtn.disabled = false;
            }
        });
        
        const imageUploadArea = document.getElementById('imageUploadArea');
        const imageFile = document.getElementById('imageFile');
        const imageFileName = document.getElementById('imageFileName');
        const uploadBtn = document.getElementById('uploadBtn');
        const imageProgress = document.getElementById('imageProgress');
        const imageProgressFill = document.getElementById('imageProgressFill');
        const imageResult = document.getElementById('imageResult');
        
        imageUploadArea.addEventListener('click', () => imageFile.click());
        imageUploadArea.addEventListener('dragover', (e) => { e.preventDefault(); imageUploadArea.classList.add('dragover'); });
        imageUploadArea.addEventListener('dragleave', () => imageUploadArea.classList.remove('dragover'));
        imageUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            imageUploadArea.classList.remove('dragover');
            if (e.dataTransfer.files.length > 0) {
                imageFile.files = e.dataTransfer.files;
                handleImageFileSelect();
            }
        });
        imageFile.addEventListener('change', handleImageFileSelect);
        
        function handleImageFileSelect() {
            if (imageFile.files.length > 0) {
                imageFileName.textContent = 'Selected: ' + imageFile.files[0].name;
                uploadBtn.disabled = false;
            }
        }
        
        uploadBtn.addEventListener('click', () => uploadImageChunked());
        
       async function uploadImageChunked() {
    const file = imageFile.files[0];
    if (!file) return;
    uploadBtn.disabled = true;
    imageProgress.style.display = 'block';
    imageResult.style.display = 'none';
    const chunkSize = 1024 * 1024;
    const totalChunks = Math.ceil(file.size / chunkSize);
    
    console.log('Starting upload for file:', file.name);
    console.log('CSRF Token:', csrfToken);
    
    const checksum = await calculateChecksum(file);
    console.log('Checksum calculated:', checksum);
    
    try {
        console.log('Calling initialize API...');
        const initResponse = await fetch('/api/uploads/initialize', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ filename: file.name, mime_type: file.type, total_size: file.size, total_chunks: totalChunks, checksum: checksum })
        });
        
        console.log('Initialize response status:', initResponse.status);
        console.log('Initialize response headers:', initResponse.headers);
        
        const responseText = await initResponse.text();
        console.log('Raw response text:', responseText);
        
        const initData = JSON.parse(responseText);
        console.log('Parsed data:', initData);
        
        const uploadId = initData.upload_id;
        
        for (let i = 0; i < totalChunks; i++) {
            const start = i * chunkSize;
            const end = Math.min(start + chunkSize, file.size);
            const chunk = file.slice(start, end);
            const formData = new FormData();
            formData.append('upload_id', uploadId);
            formData.append('chunk_index', i);
            formData.append('chunk', chunk);
            await fetch('/api/uploads/chunk', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken }, body: formData });
            const progress = Math.round(((i + 1) / totalChunks) * 100);
            imageProgressFill.style.width = progress + '%';
            imageProgressFill.textContent = progress + '%';
        }
        
        const completeResponse = await fetch('/api/uploads/complete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ upload_id: uploadId })
        });
        const completeData = await completeResponse.json();
        if (completeData.success) {
            imageResult.className = 'result success';
            imageResult.style.display = 'block';
            imageResult.innerHTML = `<h3>Image Uploaded Successfully!</h3><p><strong>Upload ID:</strong> ${uploadId}</p><p>Image variants will be generated when linked to a product.</p>`;
        }
    } catch (error) {
        console.error('Upload error:', error);
        console.error('Error stack:', error.stack);
        imageResult.className = 'result error';
        imageResult.style.display = 'block';
        imageResult.innerHTML = '<h3>Error</h3><p>' + error.message + '</p>';
    } finally {
        uploadBtn.disabled = false;
    }
}
        
        async function calculateChecksum(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = async (e) => {
                    const buffer = e.target.result;
                    const hashBuffer = await crypto.subtle.digest('SHA-256', buffer);
                    const hashArray = Array.from(new Uint8Array(hashBuffer));
                    const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
                    resolve(hashHex);
                };
                reader.onerror = reject;
                reader.readAsArrayBuffer(file);
            });
        }
    </script>
</body>
</html>