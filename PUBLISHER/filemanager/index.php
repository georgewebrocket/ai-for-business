<?php

require_once('../php/config.php');
require_once('../php/db.php');
require_once('../php/dataobjects.php');
require_once('../php/utils.php');
require_once('../php/start.php');
require_once('../php/session.php');

function fail_request($message, $status = 400) {
    http_response_code($status);
    echo $message;
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalize_relative_folder($path) {
    $path = str_replace('\\', '/', (string)$path);
    $path = trim($path, "/ \t\n\r\0\x0B");
    $path = preg_replace('#/+#', '/', $path);

    if ($path === '') {
        $path = 'images';
    }
    if (preg_match('/(^|\/)\.\.?(\/|$)/', $path) || preg_match('/^[A-Za-z]:/', $path) || strpos($path, "\0") !== false) {
        fail_request('Invalid folder.', 400);
    }
    if ($path !== 'images' && strpos($path, 'images/') !== 0) {
        fail_request('Folder is not allowed.', 403);
    }

    return $path;
}

function normalize_script_folder_depth($value) {
    $depth = filter_var($value, FILTER_VALIDATE_INT);
    if ($depth === false || $depth < 1 || $depth > 6) {
        return 2;
    }
    return $depth;
}

function folder_is_inside($folder, $rootFolder) {
    $folder = trim(str_replace('\\', '/', (string)$folder), '/');
    $rootFolder = trim(str_replace('\\', '/', (string)$rootFolder), '/');
    return $folder === $rootFolder || strpos($folder, $rootFolder . '/') === 0;
}

function ensure_inside($path, $allowedRoot) {
    $path = rtrim(str_replace('\\', '/', $path), '/');
    $allowedRoot = rtrim(str_replace('\\', '/', $allowedRoot), '/');
    return $path === $allowedRoot || strpos($path, $allowedRoot . '/') === 0;
}

function safe_directory_path($siteRoot, $allowedRoot, $relativeFolder, $create = false) {
    $candidate = rtrim($siteRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeFolder);

    if ($create && !is_dir($candidate)) {
        $parent = realpath(dirname($candidate));
        if ($parent === false || !ensure_inside($parent, $allowedRoot)) {
            fail_request('Invalid folder.', 403);
        }
        if (!mkdir($candidate, 0755, true) && !is_dir($candidate)) {
            fail_request('Could not create folder.', 500);
        }
    }

    $real = realpath($candidate);
    if ($real === false || !is_dir($real) || !ensure_inside($real, $allowedRoot)) {
        fail_request('Invalid folder.', 403);
    }

    return $real;
}

function sanitize_filename($name) {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $base = pathinfo($name, PATHINFO_FILENAME);
    $base = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $base);
    $base = trim(preg_replace('/\s+/', '_', $base), '._- ');
    if ($base === '') {
        $base = 'file';
    }
    return $ext ? "{$base}.{$ext}" : $base;
}

function sanitize_folder_name($name) {
    $name = preg_replace('/[^A-Za-z0-9._ -]+/', '_', (string)$name);
    $name = trim(preg_replace('/\s+/', '_', $name), '._- ');
    if ($name === '' || $name === '.' || $name === '..') {
        fail_request('Invalid folder name.', 400);
    }
    return $name;
}

function sanitize_item_name($name) {
    $name = (string)$name;
    if ($name !== basename($name) || strpos($name, "\0") !== false || $name === '.' || $name === '..') {
        fail_request('Invalid item.', 400);
    }
    return $name;
}

function unique_path($dir, $filename) {
    $target = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    if (!file_exists($target)) {
        return $target;
    }

    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $i = 1;
    do {
        $candidate = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $base . '-' . $i . ($ext ? ".{$ext}" : '');
        $i++;
    } while (file_exists($candidate));

    return $candidate;
}

function path_to_url($baseUrl, $relativePath) {
    $segments = array_map('rawurlencode', explode('/', trim($relativePath, '/')));
    return rtrim($baseUrl, '/') . '/' . implode('/', $segments);
}

function normalize_base_url($baseUrl) {
    return trim((string)$baseUrl);
}

$script_folder_depth = normalize_script_folder_depth($_REQUEST['scriptfolderdepth'] ?? 2);
$siteRoot = realpath(dirname(__DIR__, $script_folder_depth));
if ($siteRoot === false) {
    fail_request('Site root not found.', 500);
}

$allowedRoot = realpath($siteRoot . DIRECTORY_SEPARATOR . 'images');
if ($allowedRoot === false || !is_dir($allowedRoot)) {
    fail_request('Images folder not found.', 500);
}

$baseURL = normalize_base_url($_REQUEST['baseurl'] ?? '');
$current_folder = normalize_relative_folder($_REQUEST['currentfolder'] ?? 'images');
$root_folder = normalize_relative_folder($_REQUEST['rootfolder'] ?? $current_folder);
if (!folder_is_inside($current_folder, $root_folder)) {
    fail_request('Folder is outside the starting folder.', 403);
}
$parent_element = preg_replace('/[^A-Za-z0-9_-]/', '', $_REQUEST['parentelement'] ?? '');
$readonly = isset($_REQUEST['readonly']);

$rootDirectory = safe_directory_path($siteRoot, $allowedRoot, $root_folder, true);
$directory = safe_directory_path($siteRoot, $allowedRoot, $current_folder, true);
$relativePath = trim(str_replace('\\', '/', substr($directory, strlen($siteRoot))), '/');
$fullURLPath = rtrim($baseURL, '/') . '/' . trim($relativePath, '/') . '/';
$parent_folder = dirname($current_folder);
if ($parent_folder === '.' || !folder_is_inside($parent_folder, $root_folder)) {
    $parent_folder = $root_folder;
}
$has_parent = $current_folder !== $root_folder;

$csrfKey = app::$slug . 'FILEMANAGER_CSRF';
if (empty($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[$csrfKey];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, $postedToken)) {
        fail_request('Invalid request token.', 403);
    }
}

$ALLOWED_EXT = ['jpg','jpeg','png','gif','bmp','webp','svg','pdf','mp4'];
$ALLOWED_MIME = [
    'image/jpeg','image/png','image/gif','image/bmp','image/webp','image/svg+xml',
    'application/pdf',
    'video/mp4'
];
$MAX_BYTES = 100 * 1024 * 1024;

if (isset($_POST['create_folder'])) {
    if ($readonly) {
        fail_request('Read only.', 403);
    }
    $folderName = sanitize_folder_name($_POST['folder_name'] ?? '');
    $candidateFolder = $directory . DIRECTORY_SEPARATOR . $folderName;
    if (is_dir($candidateFolder)) {
        echo 'Folder already exists!';
        exit;
    }
    $newFolder = safe_directory_path($siteRoot, $allowedRoot, $current_folder . '/' . $folderName, true);
    if (!folder_is_inside(trim(str_replace('\\', '/', substr($newFolder, strlen($siteRoot))), '/'), $root_folder)) {
        fail_request('Folder is outside the starting folder.', 403);
    }
    echo is_dir($newFolder) ? 'Folder created successfully!' : 'Folder creation failed.';
    exit;
}

if (isset($_FILES['file_upload'])) {
    if ($readonly) {
        fail_request('Read only.', 403);
    }

    if (!isset($_FILES['file_upload']['error']) || is_array($_FILES['file_upload']['error'])) {
        fail_request('Invalid upload.', 400);
    }
    if ($_FILES['file_upload']['error'] !== UPLOAD_ERR_OK) {
        fail_request('Upload error code: ' . (int)$_FILES['file_upload']['error'], 400);
    }
    if ($_FILES['file_upload']['size'] > $MAX_BYTES) {
        fail_request('File too large. Max ' . round($MAX_BYTES / 1024 / 1024) . 'MB.', 400);
    }

    $tmp = $_FILES['file_upload']['tmp_name'];
    $origName = $_FILES['file_upload']['name'];
    $fileType = @mime_content_type($tmp) ?: '';
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if (!in_array($ext, $ALLOWED_EXT, true) || !in_array($fileType, $ALLOWED_MIME, true)) {
        fail_request('File type not allowed.', 400);
    }

    $safeName = sanitize_filename($origName);
    $uploadPath = unique_path($directory, $safeName);
    if (!ensure_inside(dirname($uploadPath), $allowedRoot)) {
        fail_request('Invalid upload folder.', 403);
    }

    if (!move_uploaded_file($tmp, $uploadPath)) {
        fail_request('Upload failed!', 500);
    }

    $imageTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (in_array($fileType, $imageTypes, true)) {
        $imgSize = @getimagesize($uploadPath);
        $width = 0;
        if ($imgSize) {
            list($width, $height) = $imgSize;
            if ($width > 1500) {
                $newWidth = 1500;
                $newHeight = intval(($newWidth / $width) * $height);
                switch ($fileType) {
                    case 'image/jpeg':
                        $src = @imagecreatefromjpeg($uploadPath);
                        break;
                    case 'image/png':
                        $src = @imagecreatefrompng($uploadPath);
                        break;
                    case 'image/webp':
                        $src = @imagecreatefromwebp($uploadPath);
                        break;
                    default:
                        $src = null;
                }

                if ($src) {
                    $resized = imagecreatetruecolor($newWidth, $newHeight);
                    imagecopyresampled($resized, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    switch ($fileType) {
                        case 'image/jpeg':
                            imagejpeg($resized, $uploadPath, 85);
                            break;
                        case 'image/png':
                            imagepng($resized, $uploadPath);
                            break;
                        case 'image/webp':
                            imagewebp($resized, $uploadPath);
                            break;
                    }
                    imagedestroy($src);
                    imagedestroy($resized);
                }
            }
        }
        echo 'Image uploaded' . ($width > 1500 ? ' and resized' : '') . '!';
        exit;
    }

    echo 'File uploaded!';
    exit;
}

if (isset($_POST['delete_file'])) {
    if ($readonly) {
        fail_request('Read only.', 403);
    }
    $item = sanitize_item_name($_POST['delete_file']);
    $deletePath = $directory . DIRECTORY_SEPARATOR . $item;
    $realDeletePath = realpath($deletePath);
    if ($realDeletePath === false || !ensure_inside($realDeletePath, $allowedRoot)) {
        fail_request('Item not found!', 404);
    }

    if (is_dir($realDeletePath)) {
        if (@rmdir($realDeletePath)) {
            echo 'Folder deleted!';
        } else {
            fail_request('Folder is not empty.', 400);
        }
    } elseif (is_file($realDeletePath)) {
        if (@unlink($realDeletePath)) {
            echo 'File deleted!';
        } else {
            fail_request('Delete failed.', 500);
        }
    } else {
        echo 'Item not found!';
    }
    exit;
}

function listFilesAndFolders($directory, $fullURLPath, $baseURL, $current_folder, $root_folder, $script_folder_depth, $has_parent, $parent_folder, $parent_element, $readonly) {
    $items = @scandir($directory) ?: [];
    $folders = [];
    $files = [];

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $directory . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            $folders[] = $item;
        } else {
            $files[] = $item;
        }
    }

    natcasesort($folders);
    natcasesort($files);

    echo "<ul class='file-grid'>";

    if ($has_parent) {
        $parentParams = [
            'baseurl' => $baseURL,
            'currentfolder' => $parent_folder,
            'rootfolder' => $root_folder,
            'scriptfolderdepth' => $script_folder_depth,
            'parentelement' => $parent_element,
        ];
        if ($readonly) {
            $parentParams['readonly'] = 1;
        }
        $parentLink = '?' . http_build_query($parentParams);
        echo "<li><div class='file-container'><div class='file-icon'>Back</div><div class='file-name'><a href='" . e($parentLink) . "'><strong>Go to Parent Folder</strong></a></div></div></li>";
    }

    foreach ($folders as $folder) {
        $newFolderPath = $current_folder . '/' . $folder;
        $folderParams = [
            'baseurl' => $baseURL,
            'currentfolder' => $newFolderPath,
            'rootfolder' => $root_folder,
            'scriptfolderdepth' => $script_folder_depth,
            'parentelement' => $parent_element,
        ];
        if ($readonly) {
            $folderParams['readonly'] = 1;
        }
        $folderLink = '?' . http_build_query($folderParams);
        $folderJson = e(json_encode($folder, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        echo "<li><div class='file-container' style='position:relative;padding-bottom:20px;'>";
        echo "<div class='file-icon'>Folder</div>";
        echo "<div class='file-name'><a href='" . e($folderLink) . "'><strong>" . e($folder) . "</strong></a></div>";
        if (!$readonly) {
            echo "<button class='delete-btn' type='button' onclick='deleteItem({$folderJson})'>Delete</button>";
        }
        echo "</div></li>";
    }

    foreach ($files as $file) {
        $filePath = $directory . DIRECTORY_SEPARATOR . $file;
        $fileURL = $fullURLPath . $file;
        $fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $fileJson = e(json_encode($file, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $parentJson = e(json_encode($parent_element, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $urlJson = e(json_encode($fileURL, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        echo "<li><div class='file-container' style='position:relative;padding-bottom:20px;'>";

        if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'], true)) {
            echo "<img src='" . e($fileURL) . "' alt='" . e($file) . "' loading='lazy' class='lazy-thumb' ondblclick='sendImageToParent({$parentJson}, {$urlJson})'><br>";
        } elseif ($fileExt === 'pdf') {
            echo "<div class='file-icon' aria-label='PDF file' ondblclick='sendImageToParent({$parentJson}, {$urlJson})'>PDF</div>";
        } elseif ($fileExt === 'mp4') {
            echo "<video width='160' height='90' controls preload='metadata' ondblclick='sendImageToParent({$parentJson}, {$urlJson})' style='margin-top:5px; cursor:pointer;'><source src='" . e($fileURL) . "' type='video/mp4'></video>";
        } else {
            echo "<div class='file-icon'>File</div>";
        }

        echo "<div class='file-name'><a href='" . e($fileURL) . "' target='_blank' rel='noopener'>" . e($file) . "</a></div>";

        if (!$readonly) {
            echo "<button class='delete-btn' type='button' onclick='deleteItem({$fileJson})'>Delete</button>";
        }

        echo "</div></li>";
    }

    echo "</ul>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager</title>
    <style>
        body { font-family: Arial, sans-serif; background-color:#fff; }
        .toolbar { margin-bottom: 20px; }
        .toolbar button { padding: 8px 15px; margin-right: 10px; cursor: pointer; }
        .file-grid {
            list-style-type: none;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }
        .file-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            min-height: 185px;
            box-sizing: border-box;
            padding: 10px;
            padding-bottom: 34px;
            border: 1px solid #ddd;
            border-radius: 8px;
            text-align: center;
            position: relative;
        }
        .file-icon {
            width: 120px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .file-name {
            font-size: 13px;
            word-break: break-word;
            margin-top: 5px;
            max-height: 38px;
            overflow: hidden;
        }
        .lazy-thumb {
            max-width: 100px;
            max-height: 100px;
            margin-top: 5px;
            border: 1px solid #ccc;
            opacity: 0;
            transition: opacity 0.5s;
        }
        .lazy-thumb.loaded { opacity: 1; }
        video {
            width: 120px;
            height: 100px;
            object-fit: contain;
        }
        button {
            margin-top: 5px;
            padding: 5px 8px;
            border: none;
            background: #ff4d4d;
            color: white;
            cursor: pointer;
            border-radius: 4px;
        }
        button:hover { background: #d93636; }
        .create-folder-btn {
            background: #2563eb;
        }
        .create-folder-btn:hover {
            background: #1d4ed8;
        }
        .delete-btn {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: #ff4d4d;
            color: #fff;
            padding: 4px 8px;
            font-size: 12px;
        }
    </style>
    <script>
        const csrfToken = <?php echo json_encode($csrfToken); ?>;

        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll("img.lazy-thumb").forEach(img => {
                img.onload = () => img.classList.add("loaded");
            });
        });

        function createFolder() {
            let folderName = prompt("Enter folder name:");
            if (folderName) {
                let formData = new FormData();
                formData.append("csrf_token", csrfToken);
                formData.append("create_folder", "1");
                formData.append("folder_name", folderName);
                fetch("", { method: "POST", body: formData, credentials: "same-origin" })
                    .then(response => response.text())
                    .then(alert)
                    .then(() => location.reload());
            }
        }

        function uploadFile() {
            let fileInput = document.getElementById("fileInput");
            fileInput.click();
        }

        function handleFileUpload() {
            let fileInput = document.getElementById("fileInput");
            let file = fileInput.files[0];
            if (file) {
                let formData = new FormData();
                formData.append("csrf_token", csrfToken);
                formData.append("file_upload", file);
                fetch("", { method: "POST", body: formData, credentials: "same-origin" })
                    .then(response => response.text())
                    .then(alert)
                    .then(() => location.reload());
            }
        }

        function deleteItem(item) {
            if (confirm(`Are you sure you want to delete "${item}"?`)) {
                let formData = new FormData();
                formData.append("csrf_token", csrfToken);
                formData.append("delete_file", item);
                fetch("", { method: "POST", body: formData, credentials: "same-origin" })
                    .then(response => response.text())
                    .then(alert)
                    .then(() => location.reload());
            }
        }

        function sendImageToParent(parentElement, imageUrl) {
            if (window.parent && window.parent.updateImgPath) {
                window.parent.updateImgPath(parentElement, imageUrl);
            } else {
                alert("Parent function not found!");
            }
        }
    </script>
</head>
<body>
    <h2>File Manager</h2>

    <div class="toolbar">
        <?php if (!$readonly) { ?>
            <button class="create-folder-btn" type="button" onclick="createFolder()">Create Folder</button>
            <button type="button" onclick="uploadFile()">Upload File</button>
            <input type="file" id="fileInput" accept="image/*,application/pdf,video/mp4" style="display: none;" onchange="handleFileUpload()">
        <?php } ?>
    </div>

    <h3>Contents of Directory: <?php echo e($baseURL . $current_folder); ?></h3>
    <?php listFilesAndFolders($directory, $fullURLPath, $baseURL, $current_folder, $root_folder, $script_folder_depth, $has_parent, $parent_folder, $parent_element, $readonly); ?>
</body>
</html>
