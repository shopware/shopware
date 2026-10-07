<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use GuzzleHttp\Psr7\Uri;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[Package('framework')]
class UrlEncodingTwigFilter extends AbstractExtension
{
    /**
     * @return list<TwigFilter>
     */
    public function getFilters()
    {
        return [
            new TwigFilter('sw_encode_url', $this->encodeUrl(...)),
            new TwigFilter('sw_encode_media_url', $this->encodeMediaUrl(...)),
        ];
    }

    public function encodeUrl(?string $mediaUrl): ?string
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

    public function encodeMediaUrl(?MediaEntity $media): ?string
    {
        if ($media === null || !$media->hasFile()) {
            return null;
        }

        return $this->encodeUrl($media->getUrl());
    }
}
