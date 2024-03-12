<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Infrastructure\Path;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Core\Application\MediaReverseProxy;
use Shopware\Core\Content\Media\Core\Params\UrlParams;
use Shopware\Core\Content\Media\Core\Params\UrlParamsSource;
use Shopware\Core\Content\Media\Event\MediaUploadedEvent;
use Shopware\Core\Content\Media\Event\ThumbnailsGeneratedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('core')]
class BanMediaUrl
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ?MediaReverseProxy $gateway,
        private readonly Connection $connection,
        private readonly MediaUrlGenerator $generator
    ) {
    }

    public function onThumbnailsGenerated(ThumbnailsGeneratedEvent $event): void
    {
        // null since shopware.cdn.fastly.enabled is enabled
        if ($this->gateway === null) {
            return;
        }

        $params = array_map(function ($generated) {
            return new UrlParams(
                id: $generated['thumbnailId'],
                source: UrlParamsSource::THUMBNAIL,
                path: $generated['path']
            );
        }, $event->generated);

        if (empty($params)) {
            return;
        }

        $urls = $this->generator->generate($params);

        if (empty($urls)) {
            return;
        }

        $this->gateway->ban($urls);
    }

    public function onUpload(MediaUploadedEvent $event): void
    {
        // null since shopware.cdn.fastly.enabled is enabled
        if ($this->gateway === null) {
            return;
        }

        $path = $this->connection->fetchOne(
            'SELECT `path` FROM `media` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($event->getMediaId())]
        );

        if (empty($path)) {
            return;
        }

        $urls = $this->generator->generate([
            new UrlParams(
                id: $event->getMediaId(),
                source: UrlParamsSource::MEDIA,
                path: $path
            ),
        ]);

        $url = array_shift($urls);

        if ($url === null) {
            return;
        }

        $this->gateway->ban([$url]);
    }
}
