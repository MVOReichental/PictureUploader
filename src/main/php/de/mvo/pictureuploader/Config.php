<?php

namespace de\mvo\pictureuploader;

use com\selfcoders\pini\Pini;
use UnexpectedValueException;

class Config extends Pini
{
    /**
     * @var Pini
     */
    private static $pini;

    public static function getInstance()
    {
        if (self::$pini !== null) {
            return self::$pini;
        }

        self::$pini = new self(RESOURCES_ROOT . "/config.ini");

        return self::$pini;
    }

    public static function getValue($section, $property, $defaultValue = null)
    {
        $ini = self::getInstance();

        if ($section === null or $section === "") {
            $sectionInstance = $ini->getDefaultSection();
        } else {
            $sectionInstance = $ini->getSection($section);
        }

        $value = $defaultValue;

        if ($sectionInstance !== null) {
            $value = $sectionInstance->getPropertyValue($property, $value);
        }

        if ($value === null) {
            throw new UnexpectedValueException("Configuration property '" . $property . "' in section '" . $section . "' not set and does not have a default value");
        }

        return $value;
    }
}