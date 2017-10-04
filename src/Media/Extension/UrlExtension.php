<?php declare(strict_types=1);

namespace Shopware\Media\Extension;

use Shopware\Media\Event\MediaBasicLoadedEvent;
use Shopware\Media\UrlGeneratorInterface;

class UrlExtension extends MediaExtension
{
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function mediaBasicLoaded(MediaBasicLoadedEvent $event): void
    {
        foreach ($event->getMedia() as $media) {
            $media->setUrl($this->urlGenerator->getUrl($media->getFileName()));
        }
    }
}
