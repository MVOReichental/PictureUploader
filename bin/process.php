#! /usr/bin/env php
<?php
use de\mvo\pictureuploader\Album;
use de\mvo\pictureuploader\Albums;
use de\mvo\pictureuploader\Config;

require_once __DIR__ . "/../bootstrap.php";

$lockFile = Config::getValue(null, "lock-file");
$lockFileHandle = fopen($lockFile, "w+");

if (!flock($lockFileHandle, LOCK_EX | LOCK_NB)) {
    fclose($lockFileHandle);
    exit;
}

/**
 * @var $album Album
 */
foreach (Albums::getInQueue() as $album) {
    $album->process();
}

fclose($lockFileHandle);
unlink($lockFile);