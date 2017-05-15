#! /usr/bin/env php
<?php
use de\mvo\pictureuploader\Config;
use de\mvo\pictureuploader\Queue;

require_once __DIR__ . "/../bootstrap.php";

$lockFile = Config::getValue(null, "lock-file");
$lockFileHandle = fopen($lockFile, "w+");

if (!flock($lockFileHandle, LOCK_EX | LOCK_NB)) {
    fclose($lockFileHandle);
    exit;
}

$queue = Queue::get();

$queue->process();

fclose($lockFileHandle);
unlink($lockFile);