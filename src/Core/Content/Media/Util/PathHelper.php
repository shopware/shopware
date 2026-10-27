<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Util;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class PathHelper
{
    private const REGEX_URL_CREDENTIALS = '#^([a-z][a-z0-9+.\-]*://)[^/?\#]*@#i';

    public static function stripCredentials(string $url): string
    {
        return (string) preg_replace(self::REGEX_URL_CREDENTIALS, '$1', $url);
    }
}
