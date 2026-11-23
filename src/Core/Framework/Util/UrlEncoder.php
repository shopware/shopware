<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use GuzzleHttp\Psr7\Uri;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class UrlEncoder
{
    public static function encodeUrl(?string $mediaUrl): ?string
    {
        if ($mediaUrl === null) {
            return null;
        }

        try {
            $uri = new Uri($mediaUrl);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $path = self::encodeEachSegment(
            $uri->getPath(),
            static fn (string $segment): string => rawurlencode(rawurldecode($segment))
        );

        return (string) $uri->withPath($path)->withFragment('');
    }

    /**
     * Expects a raw storage path: a "%" is part of the file name and gets encoded, unlike in encodeUrl().
     */
    public static function encodePathSegments(string $path): string
    {
        return self::encodeEachSegment($path, rawurlencode(...));
    }

    /**
     * @param \Closure(string): string $encode
     */
    private static function encodeEachSegment(string $path, \Closure $encode): string
    {
        return implode('/', array_map($encode, explode('/', $path)));
    }
}
