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

        $segments = explode('/', $uri->getPath());

        foreach ($segments as $index => $segment) {
            $segments[$index] = rawurlencode(rawurldecode($segment));
        }

        return (string) $uri->withPath(implode('/', $segments))->withFragment('');
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
