<?php
namespace de\mvo\pictureuploader;

use ArrayObject;
use DirectoryIterator;

class Queue extends ArrayObject
{
    public static function get()
    {
        $list = new self;

        $queuePath = Config::getValue(null, "queue");

        if (is_dir($queuePath)) {
            foreach (new DirectoryIterator($queuePath) as $item) {
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