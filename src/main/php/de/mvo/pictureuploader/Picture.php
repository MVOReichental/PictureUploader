<?php
namespace de\mvo\pictureuploader;

use de\mvo\pictureuploader\image\Resizer;

class Picture
{
    /**
     * @var string
     */
    public $filename;
    /**
     * @var string
     */
    public $url;
    /**
     * @var string
     */
    public $hash;
    /**
     * @var boolean
     */
    public $isCover;

    public function updateHash()
    {
        $this->hash = md5_file($this->filename);
    }

    public function getResizer()
    {
        return new Resizer($this->filename);
    }
}