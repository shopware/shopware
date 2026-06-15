<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Util;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class PathHelper
{
    private const REGEX_CONTROL_AND_INVISIBLE_FORMAT_CHARACTERS = '/[\x00-\x1F\x7F\p{Cf}]/u';

    private const REGEX_NON_ASCII_AND_CONTROL_BYTE_RANGES = '/[\x00-\x1F\x7F-\xFF]/';

    public static function stripControlAndFormatChars(string $path, string $replace = ''): string
    {
        $stripped = preg_replace(self::REGEX_CONTROL_AND_INVISIBLE_FORMAT_CHARACTERS, $replace, $path);

        if ($stripped === null) {
            throw MediaException::illegalFileName($path, 'Path encoding is invalid');
        }

        return $stripped;
    }

    public static function stripNonAsciiAndControlChars(string $path, string $replace = ''): string
    {
        $stripped = preg_replace(self::REGEX_NON_ASCII_AND_CONTROL_BYTE_RANGES, $replace, $path);

        if ($stripped === null) {
            throw MediaException::illegalFileName($path, 'Path encoding is invalid');
        }

        return $stripped;
    }
}
