<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util;

use Shopware\Core\Framework\Plugin\Exception\PluginExtractionException;

class ZipUtils
{
    public static function openZip(string $filename): \ZipArchive
    {
        $stream = new \ZipArchive();

        if (($retVal = $stream->open($filename)) !== true) {
            throw new PluginExtractionException(self::getErrorMessage($retVal, $filename));
        }

        return $stream;
    }

    private static function getErrorMessage(int $retVal, string $file): string
    {
        switch ($retVal) {
            case \ZipArchive::ER_EXISTS:
                return sprintf("File '%s' already exists.", $file);
            case \ZipArchive::ER_INCONS:
                return sprintf("Zip archive '%s' is inconsistent.", $file);
            case \ZipArchive::ER_INVAL:
                return sprintf('Invalid argument (%s)', $file);
            case \ZipArchive::ER_MEMORY:
                return sprintf('Malloc failure (%s)', $file);
            case \ZipArchive::ER_NOENT:
                return sprintf("No such zip file: '%s'", $file);
            case \ZipArchive::ER_NOZIP:
                return sprintf("'%s' is not a zip archive.", $file);
            case \ZipArchive::ER_OPEN:
                return sprintf("Can't open zip file: %s", $file);
            case \ZipArchive::ER_READ:
                return sprintf('Zip read error (%s)', $file);
            case \ZipArchive::ER_SEEK:
                return sprintf('Zip seek error (%s)', $file);
            default:
                return sprintf("'%s' is not a valid zip archive, got error code: %s", $file, $retVal);
        }
    }
}
