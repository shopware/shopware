<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Util;

use GuzzleHttp\Psr7\Uri;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class PathHelper
{
    private const REGEX_CONTROL_AND_INVISIBLE_FORMAT_CHARACTERS = '/[\x00-\x1F\x7F\p{Cf}]/u';

    private const REGEX_NON_ASCII_AND_CONTROL_BYTE_RANGES = '/[\x00-\x1F\x7F-\xFF]/';

    // "scheme:/" or "scheme://", then everything without "/" containing a ":" up to an "@" not followed by "/"
    private const REGEX_VALID_URL_CREDENTIALS = '#^([^:/]+:/{1,2})[^/]*:[^/]*@(?=[^/])#s';

    // "scheme:/" or "scheme://", then everything containing a ":" up to the last "@" not followed by "/"
    private const REGEX_INVALID_URL_CREDENTIALS = '#^([^:/]+:/{1,2}).*:.*@(?=[^/])#s';

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

    /**
     * @internal
     */
    public static function stripCredentials(string $url): string
    {
        try {
            $uri = new Uri($url);

            if ($uri->getUserInfo() !== '') {
                return (string) $uri->withUserInfo('');
            }
        } catch (\InvalidArgumentException) {
            return (string) \preg_replace(self::REGEX_INVALID_URL_CREDENTIALS, '$1', $url);
        }

        return (string) \preg_replace(self::REGEX_VALID_URL_CREDENTIALS, '$1', $url);
    }
}
