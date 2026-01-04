<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; script-src-elem 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; style-src-elem https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com; connect-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; default-src 'self';">
    <title>Dashboard - MiniDrive</title>
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

        .file-row {
          @apply transition-all duration-200 hover:bg-primary/5;
        }

        .upload-zone-active {
          @apply bg-primary/10 border-primary;
        }

        .btn-icon-hover {
          @apply transition-all duration-200 hover:scale-110;
        }

        .btn-glow {
          @apply hover:shadow-2xl hover:scale-105 transform transition duration-300;
        }

        @keyframes gradient-shift {
          0%, 100% { background-position: 0% 50%; }
          50% { background-position: 100% 50%; }
        }

        .animate-gradient {
          background-size: 200% 200%;
          animation: gradient-shift 3s ease infinite;
        }

        .modal-backdrop {
          @apply fixed inset-0 bg-black/50 backdrop-blur-sm z-40 flex items-center justify-center;
        }

        .modal-content {
          @apply bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto z-50;
        }

        .modal-hidden {
          @apply hidden;
        }
      }
    </style>
</head>
<body class="bg-slate-50">
    <?php
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/db.php';

    $auth = new Auth();
    $auth->requireLogin();
    $auth->checkSessionTimeout();

    $user_id = $auth->getCurrentUser();
    $user_info = $auth->getUserInfo($user_id);
    $db = Database::getInstance()->getConnection();

    // Get user files
    $stmt = $db->prepare("SELECT * FROM files WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Recalculate storage used from actual files
    $total_storage = 0;
    foreach ($files as $file) {
        $total_storage += $file['file_size'];
    }
    
    // Update storage in database if different
    if ($total_storage != $user_info['storage_used']) {
        $stmt = $db->prepare("UPDATE users SET storage_used = ? WHERE id = ?");
        $stmt->bind_param('ii', $total_storage, $user_id);
        $stmt->execute();
        $user_info['storage_used'] = $total_storage;
    }

    // Get storage usage
    $storage_used = $user_info['storage_used'];
    $storage_quota = USER_STORAGE_QUOTA;
    $storage_percent = ($storage_used / $storage_quota) * 100;
    ?>

    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-gradient rounded-lg flex items-center justify-center shadow-lg">
                    <i class="fas fa-cloud text-xl text-white"></i>
                </div>
                <h1 class="text-2xl font-bold bg-gradient bg-clip-text text-transparent">MiniDrive</h1>
            </div>
            <div class="flex items-center space-x-6">
                <div class="hidden sm:flex items-center space-x-2 text-gray-700">
                    <i class="fas fa-user-circle text-2xl text-purple-600"></i>
                    <div>
                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($user_info['username']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user_info['email']); ?></p>
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
        <!-- Storage Stats -->
        <div class="grid md:grid-cols-3 gap-6 mb-8">
            <!-- Storage Used -->
            <div class="stat-card bg-white rounded-2xl shadow-lg p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-semibold mb-1">Storage Used</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo round($storage_used / 1024 / 1024, 2); ?> <span class="text-lg text-gray-500">MB</span></p>
                    </div>
                    <i class="fas fa-database text-4xl text-blue-100"></i>
                </div>
            </div>

            <!-- Storage Limit -->
            <div class="stat-card bg-white rounded-2xl shadow-lg p-6 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-semibold mb-1">Storage Limit</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo round($storage_quota / 1024 / 1024, 0); ?> <span class="text-lg text-gray-500">MB</span></p>
                    </div>
                    <i class="fas fa-cube text-4xl text-purple-100"></i>
                </div>
            </div>

            <!-- Files Count -->
            <div class="stat-card bg-white rounded-2xl shadow-lg p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-semibold mb-1">Files Uploaded</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo count($files); ?> <span class="text-lg text-gray-500">files</span></p>
                    </div>
                    <i class="fas fa-folder-open text-4xl text-green-100"></i>
                </div>
            </div>
        </div>

        <!-- Storage Progress Bar -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-bold text-gray-800">Storage Usage</h3>
                <span class="text-sm font-semibold text-purple-600 bg-purple-50 px-3 py-1 rounded-full"><?php echo round($storage_percent, 1); ?>%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                <div class="bg-gradient h-4 rounded-full transition-all duration-500 flex items-center justify-end pr-2" style="width: <?php echo $storage_percent; ?>%">
                    <?php if ($storage_percent > 10): ?>
                        <span class="text-xs font-bold text-white"><?php echo round($storage_percent, 0); ?>%</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($storage_percent > 80): ?>
                <p class="text-xs text-orange-600 mt-2 flex items-center"><i class="fas fa-exclamation-triangle mr-1"></i>Storage almost full!</p>
            <?php endif; ?>
        </div>

        <!-- Upload Area -->
<div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
    <div id="uploadArea"
         class="border-4 border-dashed border-purple-300 rounded-2xl
                p-8 text-center cursor-pointer
                hover:bg-purple-50 transition-all duration-300
                bg-gradient-light">

        <i class="fas fa-cloud-upload-alt text-4xl text-purple-500 mb-3 block"></i>

        <h3 class="text-xl font-bold text-gray-800 mb-1">
            Drop files here or click to upload
        </h3>

        <p class="text-gray-600 text-xs mb-3">
            <i class="fas fa-info-circle"></i>
            Max file size: 10MB | Max uploads per hour: 20
        </p>

        <p class="text-gray-500 text-[11px]">
            Supports all file types • Automatic encryption for large files
        </p>

        <input type="file" id="fileInput" multiple hidden>
    </div>

    <!-- Upload Progress -->
    <div id="uploadProgress" class="mt-4 hidden">
        <div class="flex items-center justify-between mb-1">
            <p class="text-gray-700 font-semibold text-sm">Uploading...</p>
            <span id="uploadPercent" class="text-sm font-bold text-purple-600">0%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
            <div id="progressBar"
                 class="bg-gradient h-2 rounded-full transition-all duration-300"
                 style="width: 0%">
            </div>
        </div>
    </div>
</div>


        <!-- Files List -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-100 bg-gradient-light">
                <div class="flex items-center space-x-3 mb-4">
                    <i class="fas fa-list text-purple-600 text-xl"></i>
                    <h2 class="text-xl font-bold text-gray-800">Your Files</h2>
                    <span class="ml-auto bg-white px-3 py-1 rounded-full text-sm font-semibold text-gray-600"><?php echo count($files); ?> files</span>
                </div>
                <!-- Search Bar -->
                <div class="relative">
                    <label for="fileSearch" class="sr-only">Search files by name</label>
                    <input type="text" id="fileSearch" placeholder="Search files by name..." 
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none text-sm"
                           autocomplete="off">
                    <i class="fas fa-search absolute right-3 top-3.5 text-gray-400"></i>
                </div>
                <div class="mt-2 text-xs text-gray-500 flex items-center space-x-2">
                    <i class="fas fa-info-circle"></i>
                    <span id="searchResultCount">Showing <?php echo count($files); ?> files</span>
                </div>
            </div>

            <?php if (empty($files)): ?>
                <div class="px-6 py-16 text-center">
                    <i class="fas fa-inbox text-6xl text-gray-200 mb-4 block"></i>
                    <p class="text-gray-600 text-lg font-semibold mb-2">No files yet</p>
                    <p class="text-gray-500">Upload your first file to get started</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700">Filename</th>
                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700">Size</th>
                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700">Uploaded</th>
                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700">Status</th>
                                <th class="px-6 py-4 text-right text-sm font-bold text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file): ?>
                                <tr class="file-row border-b border-gray-100 hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-3 cursor-pointer file-preview-click" data-file-id="<?php echo $file['id']; ?>" data-file-name="<?php echo htmlspecialchars($file['original_filename']); ?>">
                                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-file text-purple-600"></i>
                                            </div>
                                            <div>
                                                <p class="text-gray-800 font-semibold hover:text-purple-600"><?php echo htmlspecialchars(substr($file['original_filename'], 0, 40)); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo pathinfo($file['original_filename'], PATHINFO_EXTENSION); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 font-semibold"><?php echo round($file['file_size'] / 1024, 2); ?> KB</td>
                                    <td class="px-6 py-4 text-gray-600 text-sm"><?php echo date('M d, Y · H:i', strtotime($file['created_at'])); ?></td>
                                    <td class="px-6 py-4">
                                        <?php if ($file['is_encrypted']): ?>
                                            <span class="inline-flex items-center space-x-1 text-xs bg-green-100 text-green-700 px-3 py-1 rounded-full font-semibold">
                                                <i class="fas fa-lock"></i>
                                                <span>Encrypted</span>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center space-x-1 text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded-full font-semibold">
                                                <i class="fas fa-check-circle"></i>
                                                <span>Secure</span>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end space-x-2">
                                            <button class="btn-icon-hover p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg file-preview-btn" data-file-id="<?php echo $file['id']; ?>" data-file-name="<?php echo htmlspecialchars($file['original_filename']); ?>" title="Preview">
                                                <i class="fas fa-eye text-lg"></i>
                                            </button>
                                            <a href="download.php?id=<?php echo $file['id']; ?>" class="btn-icon-hover p-2 text-blue-600 hover:bg-blue-50 rounded-lg" title="Download">
                                                <i class="fas fa-download text-lg"></i>
                                            </a>
                                            <button class="btn-icon-hover p-2 text-green-600 hover:bg-green-50 rounded-lg file-share-btn" data-file-id="<?php echo $file['id']; ?>" title="Share">
                                                <i class="fas fa-share-alt text-lg"></i>
                                            </button>
                                            <button class="btn-icon-hover p-2 text-red-600 hover:bg-red-50 rounded-lg file-delete-btn" data-file-id="<?php echo $file['id']; ?>" title="Delete">
                                                <i class="fas fa-trash-alt text-lg"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal-backdrop modal-hidden">
        <div class="modal-content">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gradient-light">
                <h3 class="text-lg font-bold text-gray-800 flex items-center space-x-2">
                    <i class="fas fa-eye text-purple-600"></i>
                    <span>File Preview</span>
                </h3>
                <button id="closePreviewBtn" class="text-gray-500 hover:text-gray-700 text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <div id="previewContent" class="flex items-center justify-center min-h-80 bg-gray-50 rounded-xl">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin text-4xl text-purple-600 mb-4"></i>
                        <p class="text-gray-600">Loading preview...</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <p id="fileName" class="text-sm text-gray-600 font-semibold"></p>
                    <a id="downloadLink" href="#" class="inline-flex items-center space-x-2 px-4 py-2 bg-gradient text-white rounded-lg hover:shadow-lg transition">
                        <i class="fas fa-download"></i>
                        <span>Download</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div id="shareModal" class="modal-backdrop modal-hidden">
        <div class="modal-content">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gradient-light">
                <h3 class="text-lg font-bold text-gray-800 flex items-center space-x-2">
                    <i class="fas fa-share-alt text-green-600"></i>
                    <span>Share File</span>
                </h3>
                <button id="closeShareBtn" class="text-gray-500 hover:text-gray-700 text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form id="shareForm" class="space-y-4">
                    <div>
                        <label for="shareEmail" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-envelope text-green-600 mr-2"></i>
                            Email Address
                        </label>
                        <input type="email" id="shareEmail" required 
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-green-500 focus:outline-none"
                               placeholder="user@example.com">
                    </div>
                    <div>
                        <label for="sharePermission" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-shield-alt text-green-600 mr-2"></i>
                            Permission Level
                        </label>
                        <select id="sharePermission" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-green-500 focus:outline-none">
                            <option value="viewer">Viewer (Read Only)</option>
                            <option value="editor">Editor (Can Modify)</option>
                        </select>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-700 flex items-start space-x-2">
                        <i class="fas fa-info-circle mt-0.5 flex-shrink-0"></i>
                        <p>The user will receive a notification and can download the file from their shared files.</p>
                    </div>
                    <div class="flex space-x-3 pt-4">
                        <button type="submit" class="flex-1 px-4 py-3 bg-gradient text-white font-bold rounded-lg hover:shadow-lg transition flex items-center justify-center space-x-2">
                            <i class="fas fa-check"></i>
                            <span>Share File</span>
                        </button>
                        <button type="button" id="cancelShareBtn" class="flex-1 px-4 py-3 bg-gray-100 text-gray-700 font-bold rounded-lg hover:bg-gray-200 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/dashboard.js"></script>
</body>
</html>
