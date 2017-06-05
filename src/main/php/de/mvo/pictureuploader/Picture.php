<?php
namespace de\mvo\pictureuploader;

use de\mvo\pictureuploader\image\Resizer;

class Picture
{
    /**
     * @var string
     */
    public $originalFilename;
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

    public function __construct($originalFilename)
    {
        $this->originalFilename = $originalFilename;
        $this->hash = md5_file($this->originalFilename);
    }

    public function getResizer()
    {
        return new Resizer($this->originalFilename);
    }
}