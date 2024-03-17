<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Infrastructure\Path;

use Shopware\Core\Content\Media\Core\Application\AbstractMediaUrlGenerator;
use Shopware\Core\Content\Media\Core\Application\MediaReverseProxy;
use Shopware\Core\Content\Media\Core\Params\UrlParams;
use Shopware\Core\Content\Media\Core\Params\UrlParamsSource;
use Shopware\Core\Content\Media\Event\MediaPathChangedEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class BanMediaUrl
{
    /**
     * @internal
     */
    public function __construct(
        private readonly MediaReverseProxy $gateway,
        private readonly AbstractMediaUrlGenerator $generator
    ) {
    }

    public function changed(MediaPathChangedEvent $event): void
    {
        if (!$this->gateway->enabled()) {
            return;
        }

        $params = [];
        foreach ($event->changed as $changed) {
            if (isset($changed['thumbnailId'])) {
                $params[] = new UrlParams(
                    id: $changed['thumbnailId'],
                    source: UrlParamsSource::THUMBNAIL,
                    path: $changed['path']
                );

                continue;
            }

            $params[] = new UrlParams(
                id: $changed['mediaId'],
                source: UrlParamsSource::MEDIA,
                path: $changed['path']
            );
        }

        if (empty($params)) {
            return;
        }

        $urls = $this->generator->generate($params);

        if (empty($urls)) {
            return;
        }

        $this->gateway->ban($urls);
    }
}
