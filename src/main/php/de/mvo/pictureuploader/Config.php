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

    private static function getInstance()
    {
        if (self::$pini !== null) {
            return self::$pini;
        }

        self::$pini = new self(RESOURCES_ROOT . "/config.ini");

        return self::$pini;
    }

    public static function getValue($property, $defaultValue = null)
    {
        $value = getenv(sprintf("PICTUREUPLOADER_%s", str_replace("-", "_", strtoupper($property))));
        if ($value !== false) {
            return $value;
        }

        $ini = self::getInstance();

        $sectionInstance = $ini->getDefaultSection();

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