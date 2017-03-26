<?php
namespace de\mvo\pictureuploader;

use DateTime;
use JsonSerializable;

class Date extends DateTime implements JsonSerializable
{
    function jsonSerialize()
    {
        return $this->format("c");
    }
}