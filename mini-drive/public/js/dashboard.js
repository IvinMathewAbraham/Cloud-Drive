const bodyDataset = document.body.dataset;
const state = {
    currentAlbumId: parseInt(bodyDataset.currentAlbumId || '0', 10),
    photoMaxSize: parseInt(bodyDataset.photoMaxSize || '0', 10),
    maxUploads: parseInt(bodyDataset.maxUploads || '0', 10)
};

let currentPhotoId = null;
let movePhotoId = null;

const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('fileInput');
const uploadProgress = document.getElementById('uploadProgress');
const progressBar = document.getElementById('progressBar');
const uploadPercent = document.getElementById('uploadPercent');

const previewModal = document.getElementById('previewModal');
const previewContent = document.getElementById('previewContent');
const previewFileName = document.getElementById('previewFileName');
const previewDownloadLink = document.getElementById('previewDownloadLink');
const closePreviewBtn = document.getElementById('closePreviewBtn');

const shareModal = document.getElementById('shareModal');
const shareForm = document.getElementById('shareForm');
const shareEmailInput = document.getElementById('shareEmail');
const sharePermissionSelect = document.getElementById('sharePermission');
const closeShareBtn = document.getElementById('closeShareBtn');
const cancelShareBtn = document.getElementById('cancelShareBtn');

const createAlbumModal = document.getElementById('createAlbumModal');
const openCreateAlbumBtn = document.getElementById('openCreateAlbum');
const closeCreateAlbumBtn = document.getElementById('closeCreateAlbum');
const cancelCreateAlbumBtn = document.getElementById('cancelCreateAlbum');
const createAlbumForm = document.getElementById('createAlbumForm');

const movePhotoModal = document.getElementById('movePhotoModal');
const closeMoveModalBtn = document.getElementById('closeMoveModal');
const cancelMoveBtn = document.getElementById('cancelMove');
const movePhotoForm = document.getElementById('movePhotoForm');
const moveTargetAlbumSelect = document.getElementById('moveTargetAlbum');

const photoGrid = document.getElementById('photoGrid');
const photoSearchInput = document.getElementById('photoSearch');
const photoCountTag = document.getElementById('photoCountTag');
const noSearchResults = document.getElementById('noSearchResults');

const albumDataElement = document.getElementById('albumData');
let albumOptions = [];
if (albumDataElement) {
    try {
        const parsed = JSON.parse(albumDataElement.dataset.albums || '[]');
        if (Array.isArray(parsed)) {
            albumOptions = parsed;
        }
    } catch (err) {
        console.error('Failed to parse album options', err);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (uploadArea && fileInput) {
        uploadArea.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', event => {
            event.preventDefault();
            uploadArea.classList.add('upload-zone-active');
        });
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('upload-zone-active');
        });
        uploadArea.addEventListener('drop', event => {
            event.preventDefault();
            uploadArea.classList.remove('upload-zone-active');
            handleFiles(event.dataTransfer.files);
        });
        fileInput.addEventListener('change', event => handleFiles(event.target.files));
    }

    if (previewModal) {
        previewModal.addEventListener('click', event => {
            if (event.target === previewModal) {
                closeModal(previewModal);
            }
        });
    }
    if (closePreviewBtn) {
        closePreviewBtn.addEventListener('click', () => closeModal(previewModal));
    }

    if (shareModal) {
        shareModal.addEventListener('click', event => {
            if (event.target === shareModal) {
                closeModal(shareModal);
            }
        });
    }
    if (closeShareBtn) {
        closeShareBtn.addEventListener('click', () => closeModal(shareModal));
    }
    if (cancelShareBtn) {
        cancelShareBtn.addEventListener('click', () => closeModal(shareModal));
    }

    if (createAlbumModal) {
        createAlbumModal.addEventListener('click', event => {
            if (event.target === createAlbumModal) {
                closeModal(createAlbumModal);
            }
        });
    }
    if (openCreateAlbumBtn) {
        openCreateAlbumBtn.addEventListener('click', () => openModal(createAlbumModal));
    }
    if (closeCreateAlbumBtn) {
        closeCreateAlbumBtn.addEventListener('click', () => closeModal(createAlbumModal));
    }
    if (cancelCreateAlbumBtn) {
        cancelCreateAlbumBtn.addEventListener('click', () => closeModal(createAlbumModal));
    }

    if (movePhotoModal) {
        movePhotoModal.addEventListener('click', event => {
            if (event.target === movePhotoModal) {
                closeModal(movePhotoModal);
            }
        });
    }
    if (closeMoveModalBtn) {
        closeMoveModalBtn.addEventListener('click', () => closeModal(movePhotoModal));
    }
    if (cancelMoveBtn) {
        cancelMoveBtn.addEventListener('click', () => closeModal(movePhotoModal));
    }

    document.addEventListener('click', handleGlobalClicks);

    if (shareForm) {
        shareForm.addEventListener('submit', handleShareSubmit);
    }

    if (createAlbumForm) {
        createAlbumForm.addEventListener('submit', handleCreateAlbum);
    }

    if (movePhotoForm) {
        movePhotoForm.addEventListener('submit', handleMovePhoto);
    }

    if (photoSearchInput && photoGrid) {
        photoSearchInput.addEventListener('input', handlePhotoSearch);
        photoSearchInput.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                photoSearchInput.value = '';
                handlePhotoSearch();
            }
        });
    }
});

function handleGlobalClicks(event) {
    const previewButton = event.target.closest('.photo-preview-btn');
    if (previewButton) {
        const card = previewButton.closest('[data-photo-card]');
        if (card) {
            openPreview(card);
        }
        return;
    }

    const shareButton = event.target.closest('.photo-share-btn');
    if (shareButton) {
        const card = shareButton.closest('[data-photo-card]');
        if (card) {
            currentPhotoId = parseInt(card.dataset.photoId || '0', 10);
            shareEmailInput.value = '';
            sharePermissionSelect.value = 'viewer';
            openModal(shareModal);
        }
        return;
    }

    const deleteButton = event.target.closest('.photo-delete-btn');
    if (deleteButton) {
        const card = deleteButton.closest('[data-photo-card]');
        if (card) {
            deletePhoto(parseInt(card.dataset.photoId || '0', 10));
        }
        return;
    }

    const moveButton = event.target.closest('.photo-move-btn');
    if (moveButton) {
        const card = moveButton.closest('[data-photo-card]');
        if (card) {
            movePhotoId = parseInt(card.dataset.photoId || '0', 10);
            populateMoveAlbumOptions();
            openModal(movePhotoModal);
        }
    }
}

function handleFiles(fileList) {
    if (!fileList || fileList.length === 0) {
        return;
    }

    Array.from(fileList).forEach(file => {
        if (!file.type.startsWith('image/')) {
            showNotification('Only image uploads are supported.', 'error');
            return;
        }
        if (state.photoMaxSize && file.size > state.photoMaxSize) {
            const maxMb = Math.round((state.photoMaxSize / (1024 * 1024)) * 10) / 10;
            showNotification(`Photo too large. Limit is ${maxMb} MB.`, 'error');
            return;
        }
        uploadPhoto(file);
    });
}

function uploadPhoto(file) {
    const formData = new FormData();
    formData.append('photo', file);
    formData.append('album_id', state.currentAlbumId.toString());

    showUploadProgress();

    fetch('upload-photo.php', {
        method: 'POST',
        body: formData
    })
        .then(async response => {
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error(text || 'Unexpected response from server');
            }
            return response.json();
        })
        .then(result => {
            hideUploadProgress();
            if (result.success) {
                showNotification('Photo uploaded successfully.', 'success');
                setTimeout(() => window.location.reload(), 900);
            } else if (result.message === 'not_authenticated') {
                showNotification('Session expired. Redirecting to login...', 'error');
                setTimeout(() => (window.location.href = 'login.php'), 1200);
            } else {
                showNotification(result.message || 'Upload failed.', 'error');
            }
        })
        .catch(error => {
            hideUploadProgress();
            showNotification(error.message || 'Upload failed.', 'error');
        });
}

function openPreview(card) {
    const photoName = card.dataset.photoName || 'Photo';
    const previewUrl = card.dataset.previewUrl;
    const downloadUrl = card.dataset.downloadUrl;

    if (!previewUrl || !downloadUrl) {
        showNotification('Preview not available.', 'error');
        return;
    }

    previewFileName.textContent = photoName;
    previewDownloadLink.href = downloadUrl;
    previewContent.innerHTML = `<img src="${previewUrl}&v=${Date.now()}" alt="${escapeHtml(photoName)}" class="max-w-full max-h-[75vh] object-contain rounded-xl">`;
    openModal(previewModal);
}

function handleShareSubmit(event) {
    event.preventDefault();
    if (!currentPhotoId) {
        showNotification('Select a photo to share.', 'error');
        return;
    }

    const payload = new URLSearchParams();
    payload.append('photo_id', currentPhotoId.toString());
    payload.append('email', shareEmailInput.value.trim());
    payload.append('permission', sharePermissionSelect.value);

    fetch('share-photo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload.toString()
    })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showNotification('Photo shared successfully.', 'success');
                closeModal(shareModal);
            } else {
                showNotification(result.message || 'Unable to share photo.', 'error');
            }
        })
        .catch(error => showNotification(error.message || 'Unable to share photo.', 'error'));
}

function deletePhoto(photoId) {
    if (!photoId || Number.isNaN(photoId)) {
        return;
    }
    if (!confirm('Delete this photo? This action cannot be undone.')) {
        return;
    }

    const payload = new URLSearchParams();
    payload.append('photo_id', photoId.toString());

    fetch('delete-photo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload.toString()
    })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showNotification('Photo deleted.', 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showNotification(result.message || 'Unable to delete photo.', 'error');
            }
        })
        .catch(error => showNotification(error.message || 'Unable to delete photo.', 'error'));
}

function handleCreateAlbum(event) {
    event.preventDefault();
    const albumName = (document.getElementById('albumName')?.value || '').trim();
    if (albumName.length === 0) {
        showNotification('Album name is required.', 'error');
        return;
    }

    fetch('create-album.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name: albumName,
            parent_id: state.currentAlbumId
        })
    })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showNotification('Album created.', 'success');
                setTimeout(() => window.location.reload(), 900);
            } else {
                showNotification(result.message || 'Unable to create album.', 'error');
            }
        })
        .catch(error => showNotification(error.message || 'Unable to create album.', 'error'));
}

function populateMoveAlbumOptions() {
    if (!moveTargetAlbumSelect) {
        return;
    }
    moveTargetAlbumSelect.innerHTML = '';

    albumOptions.forEach(album => {
        const option = document.createElement('option');
        option.value = album.id;
        let prefix = '';
        if (album.depth > 0) {
            prefix = `${'— '.repeat(album.depth)}`;
        }
        option.textContent = `${prefix}${album.name}`;
        if (album.id === state.currentAlbumId) {
            option.disabled = true;
        }
        moveTargetAlbumSelect.appendChild(option);
    });

    const availableOption = moveTargetAlbumSelect.querySelector('option:not([disabled])');
    if (availableOption) {
        moveTargetAlbumSelect.disabled = false;
        moveTargetAlbumSelect.value = availableOption.value;
    } else {
        moveTargetAlbumSelect.disabled = true;
    }
}

function handleMovePhoto(event) {
    event.preventDefault();
    if (!movePhotoId) {
        showNotification('Select a photo to move.', 'error');
        return;
    }
    if (moveTargetAlbumSelect.disabled) {
        showNotification('Create another album to move photos.', 'error');
        return;
    }
    const targetAlbumId = parseInt(moveTargetAlbumSelect.value || '0', 10);
    if (!targetAlbumId || Number.isNaN(targetAlbumId)) {
        showNotification('Choose a destination album.', 'error');
        return;
    }

    fetch('move-photo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            photo_id: movePhotoId,
            target_album_id: targetAlbumId
        })
    })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showNotification('Photo moved.', 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showNotification(result.message || 'Unable to move photo.', 'error');
            }
        })
        .catch(error => showNotification(error.message || 'Unable to move photo.', 'error'));
}

function handlePhotoSearch() {
    if (!photoGrid) {
        return;
    }
    const term = (photoSearchInput.value || '').toLowerCase().trim();
    const cards = photoGrid.querySelectorAll('[data-photo-card]');
    let visibleCount = 0;

    cards.forEach(card => {
        const name = (card.dataset.photoName || '').toLowerCase();
        const match = term === '' || name.includes(term);
        card.style.display = match ? '' : 'none';
        if (match) {
            visibleCount += 1;
        }
    });

    if (photoCountTag) {
        const total = cards.length;
        photoCountTag.textContent = term === '' ? `${total} photos` : `${visibleCount} of ${total} photos`;
    }

    if (noSearchResults) {
        noSearchResults.classList.toggle('hidden', visibleCount > 0);
    }
}

function showUploadProgress() {
    if (!uploadProgress) {
        return;
    }
    uploadProgress.classList.remove('hidden');
    progressBar.style.width = '100%';
    uploadPercent.textContent = 'Uploading';
}

function hideUploadProgress() {
    if (!uploadProgress) {
        return;
    }
    uploadProgress.classList.add('hidden');
    progressBar.style.width = '0%';
    uploadPercent.textContent = '0%';
}

function openModal(modal) {
    if (modal) {
        modal.classList.remove('modal-hidden');
    }
}

function closeModal(modal) {
    if (modal) {
        modal.classList.add('modal-hidden');
    }
    if (modal === shareModal) {
        currentPhotoId = null;
    }
    if (modal === movePhotoModal) {
        movePhotoId = null;
    }
}

function showNotification(message, type) {
    const toast = document.createElement('div');
    toast.className = `fixed top-6 right-6 px-5 py-3 rounded-lg shadow-lg flex items-center space-x-2 text-white font-semibold z-50 ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span>${escapeHtml(message)}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3200);
}

function escapeHtml(value) {
    return (value || '').replace(/[&<>"']/g, char => {
        switch (char) {
            case '&':
                return '&amp;';
            case '<':
                return '&lt;';
            case '>':
                return '&gt;';
            case '"':
                return '&quot;';
            case "'":
                return '&#039;';
            default:
                return char;
        }
    });
}
