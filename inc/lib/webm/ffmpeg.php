<?php
/*
* ffmpeg.php
* A barebones ffmpeg based webm implementation for vichan.
*/

function get_webm_info(string $filename, array $config): array
{

    $escapedFilename = escapeshellarg($filename);
    $ffprobePath = $config['webm']['ffprobe_path'];
    $command = "$ffprobePath -v quiet -print_format json -show_format -show_streams $escapedFilename";

    $output = [];
    exec($command, $output);
    $ffprobeOutput = json_decode(implode("\n", $output), true);

    $webmInfo = ['error' => validate_webm($ffprobeOutput, $config)];

    if (empty($webmInfo['error'])) {
        $webmInfo['width'] = $ffprobeOutput['streams'][0]['width'] ?? null;
        $webmInfo['height'] = $ffprobeOutput['streams'][0]['height'] ?? null;
        $webmInfo['duration'] = $ffprobeOutput['format']['duration'] ?? null;
    }

    return $webmInfo;
}

function validate_webm(array $ffprobeOutput, array $config): array
{

    if (empty($ffprobeOutput)) {
        return ['code' => 1, 'msg' => $config['error']['genwebmerror']];
    }

    $extension = pathinfo($ffprobeOutput['format']['filename'] ?? '', PATHINFO_EXTENSION);
    $formatName = $ffprobeOutput['format']['format_name'] ?? '';
    $streams = $ffprobeOutput['streams'] ?? [];

    switch ($extension) {
        case 'webm':
            if ($formatName !== 'matroska,webm') {
                return ['code' => 2, 'msg' => $config['error']['invalidwebm']];
            }
            break;

        case 'mp4':
            if (empty($streams[0]['codec_name'])) {
                return ['code' => 2, 'msg' => $config['error']['invalidwebm']];
            }
            $videoCodec = $streams[0]['codec_name'];
            $audioCodec = $streams[1]['codec_name'] ?? null;

            if ($videoCodec !== 'h264' || ($audioCodec && $audioCodec !== 'aac')) {
                return ['code' => 2, 'msg' => $config['error']['invalidwebm']];
            }
            break;

        default:
            return ['code' => 1, 'msg' => $config['error']['genwebmerror']];
    }

    if (count($streams) > 1 && !$config['webm']['allow_audio']) {
        return ['code' => 3, 'msg' => $config['error']['webmhasaudio']];
    }

    if (empty($streams[0]['width']) || empty($streams[0]['height'])) {
        return ['code' => 2, 'msg' => $config['error']['invalidwebm']];
    }

    if (($ffprobeOutput['format']['duration'] ?? 0) > $config['webm']['max_length']) {
        return ['code' => 4, 'msg' => sprintf($config['error']['webmtoolong'], $config['webm']['max_length'])];
    }

    return [];
}

function make_webm_thumbnail(array $config, string $filename, string $thumbnail, int $width, int $height, float $duration): int
{

    $fileConfig['escapedFilename'] = escapeshellarg($filename);
    $fileConfig['escapedThumbnail'] = escapeshellarg($thumbnail);
    $fileConfig['escapedWidth'] = escapeshellarg($width);
    $fileConfig['escapedHeight'] = escapeshellarg($height);
    $fileConfig['durationInSeconds'] = floor($duration / 2);
    $fileConfig['maxFrames'] = $config['webm']['thumb_keep_animation_frames'];

    $commands = build_ffmpeg_commands($config, $fileConfig);

    $result = execute_ffmpeg_command($commands['first'], $thumbnail);

    if ($result === 1) {
        // Retry with the first frame if the file is empty
        $result = execute_ffmpeg_command($commands['retry'], $thumbnail);
    }

    return $result;
}

function build_ffmpeg_commands(array $config, array $fileConfig): array
{
    $commands = [];
    $ffmpegPath = $config['webm']['ffmpeg_path'];

    if ($config['webm']['animated_thumbnail']) {
        $commands['first'] = "{$ffmpegPath} -y -strict -2 -ss {$fileConfig['durationInSeconds']} -i {$fileConfig['escapedFilename']} -v quiet -vcodec libwebp -loop 0 -vf \"fps=10,scale={$fileConfig['escapedWidth']}:{$fileConfig['escapedHeight']},select='lte(n,{$fileConfig['maxFrames']})'\" -f webp {$fileConfig['escapedThumbnail']} 2>&1";
        $commands['retry'] = "{$ffmpegPath} -y -strict -2 -ss 0 -i {$fileConfig['escapedFilename']} -v quiet -vcodec libwebp -loop 0 -vf \"fps=10,scale={$fileConfig['escapedWidth']}:{$fileConfig['escapedHeight']},select='lte(n,{$fileConfig['maxFrames']})'\" -f webp {$fileConfig['escapedThumbnail']} 2>&1";
    } else {
        $commands['first'] = "{$ffmpegPath} -y -strict -2 -ss {$fileConfig['durationInSeconds']} -i {$fileConfig['escapedFilename']} -v quiet -an -vframes 1 -vcodec libwebp -vf scale={$fileConfig['escapedWidth']}:{$fileConfig['escapedHeight']} {$fileConfig['escapedThumbnail']} 2>&1";
        $commands['retry'] = "{$ffmpegPath} -y -strict -2 -ss 0 -i {$fileConfig['escapedFilename']} -v quiet -an -vframes 1 -vcodec libwebp -vf scale={$fileConfig['escapedWidth']}:{$fileConfig['escapedHeight']} {$fileConfig['escapedThumbnail']} 2>&1";
    }

    return $commands;
}

function execute_ffmpeg_command(string $command, string $thumbnail): int
{
    $output = [];
    $returnValue = 0;

    exec($command, $output, $returnValue);

    // Work around for FFmpeg issue with zero-byte files
    if (filesize($thumbnail) === 0) {
        $returnValue = 1;
    }

    return $returnValue;
}
