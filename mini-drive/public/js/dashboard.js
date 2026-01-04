let currentFileId = null;

// DOM Elements
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('fileInput');
const uploadProgress = document.getElementById('uploadProgress');
const progressBar = document.getElementById('progressBar');
const uploadPercent = document.getElementById('uploadPercent');
const previewModal = document.getElementById('previewModal');
const shareModal = document.getElementById('shareModal');
const previewContent = document.getElementById('previewContent');
const closePreviewBtn = document.getElementById('closePreviewBtn');
const closeShareBtn = document.getElementById('closeShareBtn');
const cancelShareBtn = document.getElementById('cancelShareBtn');
const shareForm = document.getElementById('shareForm');

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Upload handlers
    if (uploadArea) {
        uploadArea.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('upload-zone-active');
        });
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('upload-zone-active');
        });
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('upload-zone-active');
            handleFiles(e.dataTransfer.files);
        });
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });
    }

    // Modal handlers - Close on backdrop click
    if (previewModal) {
        previewModal.addEventListener('click', (e) => {
            if (e.target.id === 'previewModal') {
                closePreviewModal();
            }
        });
    }
    
    if (shareModal) {
        shareModal.addEventListener('click', (e) => {
            if (e.target.id === 'shareModal') {
                closeShareModal();
            }
        });
    }

    // Close modal buttons
    if (closePreviewBtn) {
        closePreviewBtn.addEventListener('click', closePreviewModal);
    }
    if (closeShareBtn) {
        closeShareBtn.addEventListener('click', closeShareModal);
    }
    if (cancelShareBtn) {
        cancelShareBtn.addEventListener('click', closeShareModal);
    }

    // File preview button listeners
    document.addEventListener('click', (e) => {
        const previewBtn = e.target.closest('.file-preview-btn, .file-preview-click');
        if (previewBtn) {
            const fileId = previewBtn.dataset.fileId;
            const fileName = previewBtn.dataset.fileName;
            previewFile(fileId, fileName);
        }

        const shareBtn = e.target.closest('.file-share-btn');
        if (shareBtn) {
            currentFileId = shareBtn.dataset.fileId;
            openShareModal(currentFileId);
        }

        const deleteBtn = e.target.closest('.file-delete-btn');
        if (deleteBtn) {
            deleteFile(deleteBtn.dataset.fileId);
        }
    });

    // Share form submission
    if (shareForm) {
        shareForm.addEventListener('submit', handleShare);
    }

    // File search functionality
    const fileSearch = document.getElementById('fileSearch');
    const searchResultCount = document.getElementById('searchResultCount');
    const tableBody = document.querySelector('tbody');

    if (fileSearch && tableBody) {
        fileSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const rows = tableBody.querySelectorAll('tr');
            let visibleCount = 0;

            rows.forEach(row => {
                // Get the filename from the first cell
                const filenameCell = row.querySelector('td');
                const filename = filenameCell ? filenameCell.textContent.toLowerCase() : '';

                // Show or hide row based on search term
                if (searchTerm === '' || filename.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Update result count
            const totalFiles = document.querySelectorAll('tbody tr').length;
            if (searchTerm === '') {
                searchResultCount.textContent = `Showing ${totalFiles} files`;
            } else {
                searchResultCount.textContent = `Found ${visibleCount} of ${totalFiles} files`;
            }

            // Show "no results" message if needed
            if (visibleCount === 0 && searchTerm !== '') {
                if (!document.getElementById('noSearchResults')) {
                    const noResults = document.createElement('tr');
                    noResults.id = 'noSearchResults';
                    noResults.innerHTML = `
                        <td colspan="5" class="px-6 py-8 text-center">
                            <i class="fas fa-search text-4xl text-gray-300 mb-3 block"></i>
                            <p class="text-gray-600 font-semibold">No files match "${escapeHtml(searchTerm)}"</p>
                            <p class="text-gray-500 text-sm mt-2">Try a different search term</p>
                        </td>
                    `;
                    tableBody.appendChild(noResults);
                }
            } else {
                // Remove "no results" message if it exists
                const noResults = document.getElementById('noSearchResults');
                if (noResults) {
                    noResults.remove();
                }
            }
        });

        // Clear search when pressing Escape
        fileSearch.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                this.dispatchEvent(new Event('input'));
            }
        });
    }
});

function handleFiles(files) {
    for (let file of files) {
        uploadFile(file);
    }
}

function uploadFile(file) {
    const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    if (file.size > MAX_FILE_SIZE) {
        showNotification('File too large! Max size: 10MB', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    uploadProgress.classList.remove('hidden');

    fetch('upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('File uploaded successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Upload failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('Upload error: ' + error, 'error');
    });
}

function previewFile(fileId, fileName) {
    const fileName_elem = document.getElementById('fileName');
    const downloadLink = document.getElementById('downloadLink');

    previewModal.classList.remove('modal-hidden');
    fileName_elem.textContent = fileName;
    downloadLink.href = `download.php?id=${fileId}`;

    const ext = fileName.split('.').pop().toLowerCase();
    const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'];
    const textExts = ['txt', 'md', 'html', 'htm', 'css', 'js', 'php', 'json', 'xml', 'csv', 'sql', 'log'];
    const codeExts = ['py', 'java', 'cpp', 'c', 'ts', 'rb', 'go', 'rs', 'sh', 'bash'];

    if (imageExts.includes(ext)) {
        previewContent.innerHTML = `<img src="preview-file.php?id=${fileId}" alt="${fileName}" class="max-w-full max-h-96 rounded-lg object-contain">`;
    } else if (ext === 'pdf') {
        previewContent.innerHTML = `<iframe src="preview-file.php?id=${fileId}" type="application/pdf" class="w-full h-96 rounded-lg border border-gray-200"></iframe>`;
    } else if (textExts.includes(ext) || codeExts.includes(ext)) {
        fetch(`preview-file.php?id=${fileId}`)
            .then(response => response.text())
            .then(content => {
                const displayContent = content.substring(0, 5000) + (content.length > 5000 ? '\n\n... (file truncated for preview)' : '');
                previewContent.innerHTML = `
                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-auto max-h-96 text-sm"><code>${escapeHtml(displayContent)}</code></pre>
                `;
            })
            .catch(err => {
                previewContent.innerHTML = `<p class="text-gray-600">Error loading preview</p>`;
            });
    } else {
        previewContent.innerHTML = `
            <div class="text-center">
                <i class="fas fa-file text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-600 font-semibold mb-2">Preview not available</p>
                <p class="text-gray-500 text-sm">File type: .${ext.toUpperCase()}</p>
                <p class="text-gray-500 text-sm mt-4">Supported preview formats:</p>
                <ul class="text-gray-500 text-sm mt-2 space-y-1">
                    <li>📷 Images: JPG, PNG, GIF, WebP, SVG</li>
                    <li>📄 Documents: PDF, TXT, MD, HTML</li>
                    <li>💻 Code: JS, Python, Java, C++, HTML, CSS</li>
                </ul>
                <p class="text-gray-600 font-semibold mt-4">Click download to open this file</p>
            </div>
        `;
    }
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function closePreviewModal() {
    previewModal.classList.add('modal-hidden');
}

function openShareModal(fileId) {
    shareModal.classList.remove('modal-hidden');
    document.getElementById('shareEmail').value = '';
    document.getElementById('sharePermission').value = 'viewer';
}

function closeShareModal() {
    shareModal.classList.add('modal-hidden');
    currentFileId = null;
}

function handleShare(event) {
    event.preventDefault();
    const email = document.getElementById('shareEmail').value;
    const permission = document.getElementById('sharePermission').value;

    fetch('share-file.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `file_id=${currentFileId}&email=${email}&permission=${permission}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('File shared successfully!', 'success');
            closeShareModal();
        } else {
            showNotification('Share failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('Share error: ' + error, 'error');
    });
}

function deleteFile(fileId) {
    if (confirm('⚠️ Are you sure you want to delete this file? This action cannot be undone.')) {
        fetch('delete-file.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'file_id=' + fileId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('File deleted successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Delete failed: ' + data.message, 'error');
            }
        });
    }
}

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `fixed top-6 right-6 px-6 py-4 rounded-lg shadow-lg flex items-center space-x-2 text-white font-semibold z-50 animate-bounce ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    }`;
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}
