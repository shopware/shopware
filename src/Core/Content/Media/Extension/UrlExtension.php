<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Extension;

use Shopware\Core\Content\Media\Util\UrlGeneratorInterface;
use Shopware\Core\Framework\ORM\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UrlExtension implements EventSubscriberInterface
{
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public static function getSubscribedEvents()
    {
        return [
            'media.loaded' => 'mediaLoaded',
        ];
    }

    public function mediaLoaded(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $media) {
            $media->addExtension('links', new ArrayStruct([
                'url' => $this->urlGenerator->getUrl($media->getId(), $media->getMimeType()),
            ]));
        }
    }
}
