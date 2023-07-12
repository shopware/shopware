<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\PluginExtractionException;

#[Package('core')]
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
        return match ($retVal) {
            \ZipArchive::ER_EXISTS => sprintf('File \'%s\' already exists.', $file),
            \ZipArchive::ER_INCONS => sprintf('Zip archive \'%s\' is inconsistent.', $file),
            \ZipArchive::ER_INVAL => sprintf('Invalid argument (%s)', $file),
            \ZipArchive::ER_MEMORY => sprintf('Malloc failure (%s)', $file),
            \ZipArchive::ER_NOENT => sprintf('No such zip file: \'%s\'', $file),
            \ZipArchive::ER_NOZIP => sprintf('\'%s\' is not a zip archive.', $file),
            \ZipArchive::ER_OPEN => sprintf('Can\'t open zip file: %s', $file),
            \ZipArchive::ER_READ => sprintf('Zip read error (%s)', $file),
            \ZipArchive::ER_SEEK => sprintf('Zip seek error (%s)', $file),
            default => sprintf('\'%s\' is not a valid zip archive, got error code: %d', $file, $retVal),
        };
    }
}
