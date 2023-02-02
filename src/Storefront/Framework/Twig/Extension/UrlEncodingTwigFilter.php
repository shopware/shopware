<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Core\Content\Media\MediaEntity;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class UrlEncodingTwigFilter extends AbstractExtension
{
    /**
     * @return TwigFilter[]
     */
    public function getFilters()
    {
        return [
            new TwigFilter('sw_encode_url', [$this, 'encodeUrl']),
            new TwigFilter('sw_encode_media_url', [$this, 'encodeMediaUrl']),
        ];
    }

    public function encodeUrl(?string $mediaUrl): ?string
    {
        if ($mediaUrl === null) {
            return null;
        }

        $urlInfo = parse_url($mediaUrl);
        $segments = explode('/', $urlInfo['path']);

        foreach ($segments as $index => $segment) {
            $segments[$index] = rawurlencode($segment);
        }

        $path = implode('/', $segments);
        if (isset($urlInfo['query'])) {
            $path .= "?{$urlInfo['query']}";
        }

        $encodedPath = '';

        if (isset($urlInfo['scheme'])) {
            $encodedPath = "{$urlInfo['scheme']}://";
        }

        if (isset($urlInfo['host'])) {
            $encodedPath .= "{$urlInfo['host']}";
        }

        if (isset($urlInfo['port'])) {
            $encodedPath .= ":{$urlInfo['port']}";
        }

        return $encodedPath . $path;
    }

    public function encodeMediaUrl(?MediaEntity $media): ?string
    {
        if ($media === null || !$media->hasFile()) {
            return null;
        }

        return $this->encodeUrl($media->getUrl());
    }
}
