<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Util;

use GuzzleHttp\Psr7\Uri;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class PathHelper
{
    // "scheme:/" or "scheme://", then everything without "/" containing a ":" up to an "@" not followed by "/"
    private const REGEX_VALID_URL_CREDENTIALS = '#^([^:/]+:/{1,2})[^/]*:[^/]*@(?=[^/])#s';

    // "scheme:/" or "scheme://", then everything containing a ":" up to the last "@" not followed by "/"
    private const REGEX_INVALID_URL_CREDENTIALS = '#^([^:/]+:/{1,2}).*:.*@(?=[^/])#s';

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
