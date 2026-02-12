<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/photo_drive.php';

$auth = new Auth();
$auth->requireLogin();
$auth->checkSessionTimeout();

$userId = (int)$auth->getCurrentUser();
$userInfo = $auth->getUserInfo($userId);
$db = Database::getInstance()->getConnection();
$albumService = new AlbumService();

$requestedAlbumId = isset($_GET['album']) ? (int)$_GET['album'] : 0;
$currentAlbum = null;
$rootAlbum = $albumService->ensureRootAlbum($userId);

if ($requestedAlbumId > 0) {
    $currentAlbum = $albumService->getAlbumForUser($requestedAlbumId, $userId);
}

if (!$currentAlbum) {
    $currentAlbum = $rootAlbum;
}

$albumId = (int)$currentAlbum['id'];

$childAlbums = [];
$childStmt = $db->prepare('SELECT id, name, created_at FROM albums WHERE user_id = ? AND parent_id = ? ORDER BY name ASC');
$childStmt->bind_param('ii', $userId, $albumId);
$childStmt->execute();
$childResult = $childStmt->get_result();
if ($childResult) {
    while ($row = $childResult->fetch_assoc()) {
        $childAlbums[] = $row;
    }
}

$photoStmt = $db->prepare('SELECT id, original_filename, file_size, created_at, mime_type, thumbnail_path, storage_key, updated_at FROM photos WHERE album_id = ? AND deleted_at IS NULL ORDER BY created_at DESC');
$photoStmt->bind_param('i', $albumId);
$photoStmt->execute();
$photoResult = $photoStmt->get_result();
$photos = [];
if ($photoResult) {
    while ($row = $photoResult->fetch_assoc()) {
        $photos[] = $row;
    }
}

$albumTree = [];
$treeStmt = $db->prepare('SELECT id, name, parent_id, path FROM albums WHERE user_id = ? ORDER BY path ASC');
$treeStmt->bind_param('i', $userId);
$treeStmt->execute();
$treeResult = $treeStmt->get_result();
if ($treeResult) {
    while ($row = $treeResult->fetch_assoc()) {
        $albumTree[] = $row;
    }
}

$breadcrumbs = [];
$cursor = $currentAlbum;
while ($cursor) {
    $breadcrumbs = array_merge([['id' => (int)$cursor['id'], 'name' => $cursor['name']]], $breadcrumbs);
    if ($cursor['parent_id'] === null) {
        break;
    }
    $cursor = $albumService->getAlbumForUser((int)$cursor['parent_id'], $userId);
}

$storageUsed = (int)$userInfo['storage_used'];
$storageQuota = USER_PHOTO_QUOTA;
$storagePercent = $storageQuota > 0 ? min(100, ($storageUsed / $storageQuota) * 100) : 0;
$photoCount = count($photos);
$albumCount = count($childAlbums);
$maxUploadsPerHour = MAX_UPLOAD_PER_HOUR;
$photoMaxSizeMb = round(PHOTO_MAX_SIZE / 1024 / 1024, 2);

$albumOptions = [];
foreach ($albumTree as $albumEntry) {
    $depth = 0;
    $trimmedPath = trim($albumEntry['path'], '/');
    if ($trimmedPath !== '') {
        $depth = substr_count($trimmedPath, '/');
    }
    $albumOptions[] = [
        'id' => (int)$albumEntry['id'],
        'name' => $albumEntry['name'],
        'depth' => $depth
    ];
}

$albumOptionsJson = htmlspecialchars(json_encode($albumOptions), ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; script-src-elem 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; style-src-elem https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com; connect-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; default-src 'self';">
    <title>Dashboard - PhotoDrive</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <style type="text/tailwindcss">
      @theme {
        --color-primary: #667eea;
        --color-secondary: #764ba2;
        --color-accent: #667eea;
      }

      @layer components {
        .bg-gradient {
          @apply bg-gradient-to-br from-primary to-secondary;
        }

        .bg-gradient-light {
          @apply bg-gradient-to-br from-primary/5 to-secondary/5;
        }

        .stat-card {
          @apply transition-all duration-300 hover:-translate-y-1.5;
        }

        .upload-zone-active {
          @apply bg-primary/10 border-primary;
        }

        .modal-backdrop {
          @apply fixed inset-0 bg-black/50 backdrop-blur-sm z-40 flex items-center justify-center;
        }

        .modal-content {
          @apply bg-white rounded-2xl shadow-2xl max-w-xl w-full mx-4 max-h-[90vh] overflow-y-auto z-50;
        }

        .modal-hidden {
          @apply hidden;
        }
      }
    </style>
</head>
<body class="bg-slate-50" data-current-album-id="<?php echo $albumId; ?>" data-photo-max-size="<?php echo PHOTO_MAX_SIZE; ?>" data-max-uploads="<?php echo $maxUploadsPerHour; ?>">

    <nav class="bg-white shadow-lg border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-gradient rounded-lg flex items-center justify-center shadow-lg">
                    <i class="fas fa-camera text-xl text-white"></i>
                </div>
                <h1 class="text-2xl font-bold bg-gradient bg-clip-text text-transparent">PhotoDrive</h1>
            </div>
            <div class="flex items-center space-x-6">
                <div class="hidden sm:flex items-center space-x-2 text-gray-700">
                    <i class="fas fa-user-circle text-2xl text-purple-600"></i>
                    <div>
                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($userInfo['username']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($userInfo['email']); ?></p>
                    </div>
                </div>
                <a href="logout.php" class="px-4 py-2 bg-red-50 text-red-600 font-semibold rounded-lg hover:bg-red-100 transition flex items-center space-x-2">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-col lg:flex-row gap-6">
            <aside class="lg:w-72 flex-shrink-0">
                <div class="bg-white rounded-2xl shadow-lg p-5 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-gray-800 flex items-center space-x-2">
                            <i class="fas fa-folder-tree text-purple-600"></i>
                            <span>Albums</span>
                        </h2>
                        <button id="openCreateAlbum" class="px-3 py-1 text-xs font-semibold bg-gradient text-white rounded-lg hover:shadow-lg transition">
                            New Album
                        </button>
                    </div>
                    <nav id="albumList" class="space-y-1 text-sm">
                        <?php foreach ($albumTree as $albumEntry): ?>
                            <?php
                                $isActive = (int)$albumEntry['id'] === $albumId;
                                $depth = 0;
                                $trimmed = trim($albumEntry['path'], '/');
                                if ($trimmed !== '') {
                                    $depth = substr_count($trimmed, '/');
                                }
                            ?>
                            <a href="?album=<?php echo (int)$albumEntry['id']; ?>" class="flex items-center px-3 py-2 rounded-lg <?php echo $isActive ? 'bg-gradient text-white shadow' : 'text-gray-600 hover:bg-purple-50'; ?>" style="margin-left: <?php echo $depth * 12; ?>px">
                                <i class="fas fa-folder-open mr-2"></i>
                                <span class="truncate"><?php echo htmlspecialchars($albumEntry['name']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <div class="bg-white rounded-2xl shadow-lg p-5">
                    <h3 class="text-sm font-semibold text-gray-600 mb-3">Album Stats</h3>
                    <div class="space-y-3 text-sm text-gray-600">
                        <div class="flex items-center justify-between">
                            <span>Photos in album</span>
                            <span class="font-semibold text-gray-800"><?php echo $photoCount; ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Sub-albums</span>
                            <span class="font-semibold text-gray-800"><?php echo $albumCount; ?></span>
                        </div>
                    </div>
                </div>
            </aside>

            <main class="flex-1">
                <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800 flex items-center space-x-2">
                                <i class="fas fa-images text-purple-600"></i>
                                <span><?php echo htmlspecialchars($currentAlbum['name']); ?></span>
                            </h2>
                            <nav class="text-xs text-gray-500 mt-1 space-x-1">
                                <?php foreach ($breadcrumbs as $index => $crumb): ?>
                                    <a href="?album=<?php echo $crumb['id']; ?>" class="hover:text-purple-600 font-semibold"><?php echo htmlspecialchars($crumb['name']); ?></a>
                                    <?php if ($index < count($breadcrumbs) - 1): ?>
                                        <span>/</span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </nav>
                        </div>
                        <div class="flex items-center space-x-4 text-sm">
                            <div>
                                <p class="text-gray-500">Storage Used</p>
                                <p class="font-bold text-gray-800"><?php echo round($storageUsed / 1024 / 1024, 2); ?> MB</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Storage Limit</p>
                                <p class="font-bold text-gray-800"><?php echo round($storageQuota / 1024 / 1024); ?> MB</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Usage</p>
                                <p class="font-bold text-purple-600"><?php echo round($storagePercent, 1); ?>%</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                            <div class="bg-gradient h-2" style="width: <?php echo $storagePercent; ?>%"></div>
                        </div>
                        <?php if ($storagePercent > 80): ?>
                            <p class="text-xs text-orange-600 mt-2 flex items-center"><i class="fas fa-exclamation-triangle mr-2"></i>Storage almost full</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
                    <div id="uploadArea" class="border-4 border-dashed border-purple-300 rounded-2xl p-8 text-center cursor-pointer hover:bg-purple-50 transition-all duration-300 bg-gradient-light">
                        <i class="fas fa-cloud-upload-alt text-4xl text-purple-500 mb-3 block"></i>
                        <h3 class="text-xl font-bold text-gray-800 mb-1">Drop photos or click to upload</h3>
                        <p class="text-gray-600 text-xs mb-2">Max photo size: <?php echo $photoMaxSizeMb; ?> MB • Max uploads per hour: <?php echo $maxUploadsPerHour; ?></p>
                        <p class="text-gray-500 text-[11px]">PhotoDrive accepts JPG, PNG, GIF, and WebP images.</p>
                        <input type="file" id="fileInput" accept="image/*" multiple hidden>
                    </div>
                    <div id="uploadProgress" class="mt-4 hidden">
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-gray-700 font-semibold text-sm">Uploading...</p>
                            <span id="uploadPercent" class="text-sm font-bold text-purple-600">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                            <div id="progressBar" class="bg-gradient h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($childAlbums)): ?>
                    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-gray-800 flex items-center space-x-2">
                                <i class="fas fa-folder text-purple-600"></i>
                                <span>Sub-albums</span>
                            </h3>
                        </div>
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($childAlbums as $album): ?>
                                <a href="?album=<?php echo (int)$album['id']; ?>" class="block rounded-xl border border-gray-100 hover:border-purple-200 hover:shadow-lg transition p-4 bg-white">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-folder-open"></i>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($album['name']); ?></p>
                                                <p class="text-xs text-gray-500">Created <?php echo date('M d, Y', strtotime($album['created_at'])); ?></p>
                                            </div>
                                        </div>
                                        <i class="fas fa-chevron-right text-gray-300"></i>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-photo-video text-purple-600"></i>
                            <h3 class="text-lg font-bold text-gray-800">Photos</h3>
                            <span id="photoCountTag" class="bg-purple-50 text-purple-600 text-xs font-semibold px-3 py-1 rounded-full"><?php echo $photoCount; ?> photos</span>
                        </div>
                        <div class="relative">
                            <label for="photoSearch" class="sr-only">Search photos by name</label>
                            <input type="text" id="photoSearch" placeholder="Search photos..." class="w-60 px-4 py-2 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none text-sm" autocomplete="off">
                            <i class="fas fa-search absolute right-3 top-2.5 text-gray-400"></i>
                        </div>
                    </div>

                    <?php if (empty($photos)): ?>
                        <div class="py-16 text-center">
                            <i class="fas fa-image text-6xl text-gray-200 mb-4"></i>
                            <p class="text-gray-600 text-lg font-semibold mb-2">No photos in this album yet</p>
                            <p class="text-gray-500">Upload your first photo to get started.</p>
                        </div>
                    <?php else: ?>
                        <div id="photoGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                            <?php foreach ($photos as $photo): ?>
                                <?php
                                    $previewUrl = 'preview-photo.php?id=' . (int)$photo['id'];
                                    $downloadUrl = 'download-photo.php?id=' . (int)$photo['id'];
                                    $thumbUrl = $previewUrl . '&thumb=1&v=' . strtotime($photo['updated_at']);
                                ?>
                                <article class="rounded-xl border border-gray-100 hover:border-purple-200 hover:shadow-lg transition group overflow-hidden" data-photo-card data-photo-id="<?php echo (int)$photo['id']; ?>" data-photo-name="<?php echo htmlspecialchars($photo['original_filename']); ?>" data-preview-url="<?php echo $previewUrl; ?>" data-download-url="<?php echo $downloadUrl; ?>">
                                    <div class="relative h-44 bg-gray-100 overflow-hidden">
                                        <img src="<?php echo htmlspecialchars($thumbUrl); ?>" alt="<?php echo htmlspecialchars($photo['original_filename']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition"></div>
                                    </div>
                                    <div class="p-4 space-y-2">
                                        <p class="text-sm font-semibold text-gray-800 truncate" title="<?php echo htmlspecialchars($photo['original_filename']); ?>"><?php echo htmlspecialchars($photo['original_filename']); ?></p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo date('M d, Y · H:i', strtotime($photo['created_at'])); ?> · <?php echo round($photo['file_size'] / 1024, 1); ?> KB
                                        </p>
                                        <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                                            <button class="photo-preview-btn text-purple-600 hover:text-purple-800 text-sm font-semibold" data-photo-id="<?php echo (int)$photo['id']; ?>">
                                                <i class="fas fa-eye mr-1"></i>Preview
                                            </button>
                                            <div class="flex items-center space-x-3 text-gray-500">
                                                <button class="photo-share-btn hover:text-green-600" data-photo-id="<?php echo (int)$photo['id']; ?>" title="Share">
                                                    <i class="fas fa-share-alt"></i>
                                                </button>
                                                <button class="photo-move-btn hover:text-blue-600" data-photo-id="<?php echo (int)$photo['id']; ?>" title="Move">
                                                    <i class="fas fa-folder"></i>
                                                </button>
                                                <button class="photo-delete-btn hover:text-red-600" data-photo-id="<?php echo (int)$photo['id']; ?>" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div id="noSearchResults" class="hidden text-center py-12">
                        <i class="fas fa-search text-5xl text-gray-200 mb-4"></i>
                        <p class="text-gray-600 font-semibold">No photos match your search.</p>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div id="albumData" data-albums="<?php echo $albumOptionsJson; ?>" hidden></div>

    <div id="previewModal" class="modal-backdrop modal-hidden">
        <div class="modal-content">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gradient-light">
                <h3 class="text-lg font-bold text-gray-800 flex items-center space-x-2">
                    <i class="fas fa-eye text-purple-600"></i>
                    <span>Photo Preview</span>
                </h3>
                <button id="closePreviewBtn" class="text-gray-500 hover:text-gray-700 text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <div id="previewContent" class="flex items-center justify-center min-h-80 bg-gray-50 rounded-xl overflow-hidden">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin text-4xl text-purple-600 mb-4"></i>
                        <p class="text-gray-600">Loading preview...</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <p id="previewFileName" class="text-sm text-gray-600 font-semibold truncate"></p>
                    <div class="flex items-center space-x-3">
                        <a id="previewDownloadLink" href="#" class="inline-flex items-center space-x-2 px-4 py-2 bg-gradient text-white rounded-lg hover:shadow-lg transition">
                            <i class="fas fa-download"></i>
                            <span>Download</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="shareModal" class="modal-backdrop modal-hidden">
        <div class="modal-content">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gradient-light">
                <h3 class="text-lg font-bold text-gray-800 flex items-center space-x-2">
                    <i class="fas fa-share-alt text-green-600"></i>
                    <span>Share Photo</span>
                </h3>
                <button id="closeShareBtn" class="text-gray-500 hover:text-gray-700 text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form id="shareForm" class="space-y-4">
                    <div>
                        <label for="shareEmail" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                        <input type="email" id="shareEmail" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-green-500 focus:outline-none" placeholder="user@example.com">
                    </div>
                    <div>
                        <label for="sharePermission" class="block text-sm font-semibold text-gray-700 mb-2">Permission</label>
                        <select id="sharePermission" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-green-500 focus:outline-none">
                            <option value="viewer">Viewer (read only)</option>
                            <option value="editor">Editor (can download)</option>
                        </select>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-700">
                        Shared photos appear in the recipient's shared library.
                    </div>
                    <div class="flex space-x-3 pt-4">
                        <button type="submit" class="flex-1 px-4 py-3 bg-gradient text-white font-bold rounded-lg hover:shadow-lg transition flex items-center justify-center space-x-2">
                            <i class="fas fa-check"></i>
                            <span>Share Photo</span>
                        </button>
                        <button type="button" id="cancelShareBtn" class="flex-1 px-4 py-3 bg-gray-100 text-gray-700 font-bold rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="createAlbumModal" class="modal-backdrop modal-hidden">
        <div class="modal-content">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gradient-light">
                <h3 class="text-lg font-bold text-gray-800 flex items-center space-x-2">
                    <i class="fas fa-folder-plus text-purple-600"></i>
                    <span>Create Album</span>
                </h3>
                <button id="closeCreateAlbum" class="text-gray-500 hover:text-gray-700 text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form id="createAlbumForm" class="space-y-4">
                    <div>
                        <label for="albumName" class="block text-sm font-semibold text-gray-700 mb-2">Album Name</label>
                        <input type="text" id="albumName" name="name" required maxlength="120" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none" placeholder="Vacation 2025">
                    </div>
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-3 text-sm text-purple-700">
                        The album will be created inside <strong><?php echo htmlspecialchars($currentAlbum['name']); ?></strong>.
                    </div>
                    <div class="flex space-x-3 pt-4">
                        <button type="submit" class="flex-1 px-4 py-3 bg-gradient text-white font-bold rounded-lg hover:shadow-lg transition">Create</button>
                        <button type="button" id="cancelCreateAlbum" class="flex-1 px-4 py-3 bg-gray-100 text-gray-700 font-bold rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="movePhotoModal" class="modal-backdrop modal-hidden">
        <div class="modal-content">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gradient-light">
                <h3 class="text-lg font-bold text-gray-800 flex items-center space-x-2">
                    <i class="fas text-blue-600 fa-folder"></i>
                    <span>Move Photo</span>
                </h3>
                <button id="closeMoveModal" class="text-gray-500 hover:text-gray-700 text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form id="movePhotoForm" class="space-y-4">
                    <div>
                        <label for="moveTargetAlbum" class="block text-sm font-semibold text-gray-700 mb-2">Select destination album</label>
                        <select id="moveTargetAlbum" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none"></select>
                    </div>
                    <div class="flex space-x-3 pt-4">
                        <button type="submit" class="flex-1 px-4 py-3 bg-gradient text-white font-bold rounded-lg hover:shadow-lg transition">Move Photo</button>
                        <button type="button" id="cancelMove" class="flex-1 px-4 py-3 bg-gray-100 text-gray-700 font-bold rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/dashboard.js"></script>
</body>
</html>
