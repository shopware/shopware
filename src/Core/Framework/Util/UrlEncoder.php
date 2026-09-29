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

        $path = self::encodePathSegments(rawurldecode($uri->getPath()));

        return (string) $uri->withPath($path)->withFragment('');
    }

    public static function encodePathSegments(string $path): string
    {
        $segments = explode('/', $path);

        foreach ($segments as $index => $segment) {
            $segments[$index] = rawurlencode($segment);
        }

        return implode('/', $segments);
    }
}
