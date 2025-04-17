<?php

require_once 'inc/bootstrap.php';

class Banners
{
    private string $bannerDir = 'static/banners/%s/';
    private string $priorityDir = 'static/banners_priority/';
    private string $board;
    private string $ukko = 'overboard';

    public function __construct(string $board)
    {
        if (!preg_match('/^\w+$/i', $board)) {
            $this->board = $this->ukko;
        }
        $this->board = $board;
    }

    private function getFilesInDirectory(string $dir): array
    {
        if (!is_dir($dir)) {
            $dir = $this->priorityDir;
        }

        $cacheKey = "files_{$dir}";
        $listFiles = Cache::get($cacheKey);

        if (!$listFiles) {
            $listFiles = array_diff(scandir($dir, SCANDIR_SORT_NONE), ['.', '..']);
            $listFiles = array_filter($listFiles, fn ($file) => is_file($dir . $file) && $this->isImage($file));
            Cache::set($cacheKey, $listFiles, 10800);
        }

        return $listFiles;
    }

    private function isImage(string $fileName): bool
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        return in_array($extension, $allowedExtensions, true);
    }

    private function serveRandomBanner(string $dir, array $files): void
    {
        if (empty($files)) {
            http_response_code(404);
            exit;
        }

        $name = $files[array_rand($files)];
        $filePath = $dir . $name;

        if (!is_file($filePath) || !is_readable($filePath)) {
            http_response_code(404);
            exit;
        }

        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $lastModified = filemtime($filePath);
        $etag = md5_file($filePath);

        header("Content-Type: image/{$ext}");
        header("Content-Length: " . filesize($filePath));
        header("Cache-Control: public, max-age=" . (60 * 60 * 24 * 30 * 6)); // 6 months
        header("ETag: \"$etag\"");
        header("Last-Modified: " . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        header("X-Content-Type-Options: nosniff");

        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';

        if (
            (!empty($ifModifiedSince) && strtotime($ifModifiedSince) === $lastModified) ||
            (!empty($ifNoneMatch) && trim($ifNoneMatch) === $etag)
        ) {
            header("HTTP/1.1 304 Not Modified");
            exit;
        }

        readfile($filePath);
        exit;
    }

    public function serve(): void
    {
        if (!getBoardInfo($this->board)) {
            $this->board = $this->ukko;
        }

        $priorityFiles = $this->getFilesInDirectory($this->priorityDir);
        $this->bannerDir = sprintf($this->bannerDir, $this->board);
        $bannerFiles = $this->getFilesInDirectory($this->bannerDir);

        $usePriority = !empty($priorityFiles) && (mt_rand(0, 3) === 0 || empty($bannerFiles) || $this->board === $this->ukko);

        if ($usePriority) {
            $this->serveRandomBanner($this->priorityDir, $priorityFiles);
        } else {
            $this->serveRandomBanner($this->bannerDir, $bannerFiles);
        }
    }
}

try {
    $board = htmlspecialchars($_GET['board'] ?? $config['banner_overboard'], ENT_QUOTES, 'UTF-8');
    $b = new Banners($board);
    $b->serve();
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    exit;
}
