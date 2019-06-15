<?php
namespace de\mvo\pictureuploader;

use ArrayObject;
use DirectoryIterator;

class Albums extends ArrayObject
{
    public static function get()
    {
        $list = new self;

        foreach (new DirectoryIterator(Config::getValue("source")) as $year) {
            if (!$year->isDir()) {
                continue;
            }

            if (!preg_match("/^[0-9]+$/", $year->getFilename())) {
                continue;
            }

            foreach (new DirectoryIterator($year->getPathname()) as $item) {
                if (!$item->isDir()) {
                    continue;
                }

                if ($item->getFilename()[0] === ".") {
                    continue;
                }

                $album = Album::getAlbumFromYearAndFoldername($year->getFilename(), $item->getFilename());

                if ($album === null) {
                    continue;
                }

                $album->load();

                $list->append($album);
            }
        }

        $listArray = $list->getArrayCopy();

        usort($listArray, function (Album $album1, Album $album2) {
            if ($album1->date > $album2->date) {
                return -1;
            } elseif ($album1->date < $album2->date) {
                return 1;
            } else {
                return strcmp($album1->title, $album2->title);
            }
        });

        $list->exchangeArray($listArray);

        return $list;
    }

    public static function getInQueue()
    {
        $list = new self;

        if (is_dir(QUEUE_ROOT)) {
            foreach (new DirectoryIterator(QUEUE_ROOT) as $item) {
                if ($item->isDot()) {
                    continue;
                }

                if (!$item->isFile()) {
                    continue;
                }

                if ($item->getFilename()[0] === ".") {
                    continue;
                }

                $album = new Album;

                if (!$album->load($item->getPathname())) {
                    continue;
                }

                $list->append($album);
            }
        }

        return $list;
    }
}