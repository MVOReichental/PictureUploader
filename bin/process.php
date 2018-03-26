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

$iteration = 0;

while (true) {
    $queue = Albums::getInQueue();

    if (!$queue->count()) {
        break;
    }

    /**
     * @var $album Album
     */
    $album = $queue->offsetGet(0);

    // Force loading album from album json instead of queue json
    $album->load();

    $album->process();

    unlink($album->filename);

    $iteration++;

    // Give up after 100 processed albums (something must go really wrong!)
    if ($iteration >= 100) {
        break;
    }
}

fclose($lockFileHandle);
unlink($lockFile);