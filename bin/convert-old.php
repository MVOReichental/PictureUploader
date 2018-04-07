#! /usr/bin/env php
<?php
use de\mvo\pictureuploader\Album;
use de\mvo\pictureuploader\Albums;
use de\mvo\pictureuploader\Date;
use Symfony\Component\Filesystem\Filesystem;

require_once __DIR__ . "/../bootstrap.php";

$dbDumpJson = json_decode(file_get_contents("php://stdin"));

if ($dbDumpJson === null) {
    exit;
}

$dbAlbums = array();

foreach ($dbDumpJson as $dbAlbum) {
    if (!$dbAlbum->published) {
        continue;
    }

    $dbAlbums[$dbAlbum->id] = $dbAlbum;
}

/**
 * @var $album Album
 */
foreach (Albums::get() as $album) {
    $oldJsonFile = $album->getSourcePath() . "/.pictureuploader/album.json";

    if (!file_exists($oldJsonFile)) {
        continue;
    }

    $oldJson = json_decode(file_get_contents($oldJsonFile));

    if ($oldJson === null) {
        continue;
    }

    $dbId = $oldJson->id;

    if (!isset($dbAlbums[$dbId])) {
        continue;
    }

    fwrite(STDERR, sprintf("Converting album in path %s\n", $album->getSourcePath()));

    $dbAlbum = $dbAlbums[$dbId];

    $album->title = $dbAlbum->title;
    $album->text = $dbAlbum->text;
    $album->date = new Date($dbAlbum->date);
    $album->date->setTime(0, 0, 0, 0);
    $album->setNameFromTitle();
    $album->updatePictures();

    $album->isPublic = (bool)$dbAlbum->isPublic;
    $album->coverPicture = $oldJson->pictures[$dbAlbum->coverPicture - 1];
    $album->useAsYearCover = false;

    $jsonFile = $album->save();

    $filesystem = new Filesystem;

    $filesystem->copy($jsonFile, sprintf("%s/%s.json", QUEUE_ROOT, uniqid()));

    printf("%d|%d|%s\n", $dbId, $album->date->format("Y"), $album->name);
}