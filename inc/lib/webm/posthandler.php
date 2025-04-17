<?php

// Glue code for handling a Tinyboard post.
// Portions of this file are derived from Tinyboard code.

function postHandler(object $post): null | string
{
    global $board, $config;

    if (!$post->has_file) {
        return null;
    }

    foreach ($post->files as &$file) {
        if (in_array($file->extension, ['webm', 'mp4'])) {
            if ($config['webm']['use_ffmpeg']) {
                $error = handleWebmWithFFmpeg($file, $post->op, $config);
            } else {
                $error = handleWebmWithoutFFmpeg($file, $post->op, $config);
            }

            if ($error) {
                return $error;
            }
        }
    }

    return null;
}

function handleWebmWithFFmpeg(object $file, bool $op, array $config): null | string
{

    require_once dirname(__FILE__) . '/ffmpeg.php';
    $webmInfo = get_webm_info($file->file_path, $config);

    if (!empty($webmInfo['error'])) {
        return $webmInfo['error']['msg'];
    }

    $file->width = $webmInfo['width'];
    $file->height = $webmInfo['height'];

    if ($config['spoiler_images'] && isset($_POST['spoiler'])) {
        $file = webm_set_spoiler($file, $config['spoiler_image']);
    } else {
        $file = set_thumbnail_dimensions($op, $file, $config);
        $tnPath = $config['dir']['media'] . $file->file_id . '_t' . '.webp';

        if (make_webm_thumbnail($config, $file->file_path, $tnPath, $file->thumbwidth, $file->thumbheight, $webmInfo['duration']) === 0) {
            $file->thumb = $file->file_id . '_t' . '.webp';
            $file->blockhash = blockhash_hash_of_file($tnPath);
            if (!verifyUnbannedHash($config, $file->blockhash)) {
                return $config['error']['blockhash'];
            }
        } else {
            $file->thumb = 'file';
        }
    }

    return null;
}

function handleWebmWithoutFFmpeg(object $file, bool $op, array $config): null | string
{

    require_once dirname(__FILE__) . '/videodata.php';
    $videoDetails = videoData($file->file_path);

    if (!isset($videoDetails['container']) || $videoDetails['container'] !== 'webm') {
        return $webmInfo['error']['msg'];
    }

    $file->thumb = setThumbnailFromVideoDetails($file, $videoDetails, $config);
    $file->width = $videoDetails['width'] ?? $file->width;
    $file->height = $videoDetails['height'] ?? $file->height;

    if ($file->thumb !== 'file' || $file->thumb !== 'spoiler') {
        $file = set_thumbnail_dimensions($op, $file, $config);
    }

    return null;
}

function setThumbnailFromVideoDetails(object $file, array $videoDetails, array $config): string
{

    $thumbName = $config['dir']['media'] . $file->file_id . '_t' . '.webp';

    if ($config['spoiler_images'] && isset($_POST['spoiler'])) {
        $file = webm_set_spoiler($file, $config['spoiler_image']);
        return 'spoiler';
    }

    if (isset($videoDetails['frame']) && $thumbFile = fopen($thumbName, 'wb')) {
        fwrite($thumbFile, $videoDetails['frame']);
        fclose($thumbFile);
        unset($videoDetails['frame']);
        return $file->file_id . '.webm';
    }

    return 'file';
}

function set_thumbnail_dimensions(bool $op, object $file, array $config): object
{

    $tnMaxW = $op ? $config['thumb_op_width'] : $config['thumb_width'];
    $tnMaxH = $op ? $config['thumb_op_height'] : $config['thumb_height'];

    if ($file->width > $tnMaxW || $file->height > $tnMaxH) {
        $file->thumbwidth = min($tnMaxW, (int) round($file->width * $tnMaxH / $file->height));
        $file->thumbheight = min($tnMaxH, (int) round($file->height * $tnMaxW / $file->width));
    } else {
        $file->thumbwidth = $file->width;
        $file->thumbheight = $file->height;
    }

    return $file;
}

function webm_set_spoiler(object $file, string $spoiler_image): object
{

    $file->thumb = 'spoiler';
    $size = @getimagesize($spoiler_image);
    $file->thumbwidth = $size[0];
    $file->thumbheight = $size[1];
    return $file;
}
