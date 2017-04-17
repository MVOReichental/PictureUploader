<?php
namespace de\mvo\pictureuploader;

use ArrayObject;
use DirectoryIterator;

class Queue extends ArrayObject
{
    public static function get()
    {
        $list = new self;

        foreach (new DirectoryIterator(Config::getValue(null, "queue")) as $item) {
            if ($item->isDot()) {
                continue;
            }

            if (!$item->isFile()) {
                continue;
            }

            $queueItem = QueueItem::fromFile($item->getFilename());

            if ($queueItem === null) {
                continue;
            }

            $list->append($queueItem);
        }

        return $list;
    }

    public function process()
    {
        /**
         * @var $item QueueItem
         */
        foreach ($this as $item) {
            $item->process();
        }
    }
}