<?php
/* This file is dedicated to the public domain; you may do as you wish with it. */
$v = @(string)$_GET['v'];
$loop = @(boolean)$_GET['loop'];
$params = '?v=' . urlencode($v);
$title = preg_replace('/(\/\w+\/\w+\/)/i', '', $v);
?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <link rel="shortcut icon" href="/static/favicon.png">
    <title><?= htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="stylesheets/webm/playerstyle.css">
    <script src="js/webm-settings.js"></script>
    <script src="js/webm/playersettings.js"></script>
</head>
<body>
    <div id="playerheader">
        <a id="loop0" href="<?= $params; ?>&amp;loop=0"<?php if (!$loop) echo ' style="font-weight: bold"'; ?>>[play once]</a>
        <a id="loop1" href="<?= $params; ?>&amp;loop=1"<?php if ($loop) echo ' style="font-weight: bold"'; ?>>[loop]</a>
    </div>
    <div id="playercontent">
        <video controls<?php if ($loop) echo ' loop'; ?> src="<?= htmlspecialchars($v); ?>">
            Your browser does not support HTML5 video. <a href="<?= htmlspecialchars($v); ?>">[Download]</a>
        </video>
    </div>
</body>
</html>
