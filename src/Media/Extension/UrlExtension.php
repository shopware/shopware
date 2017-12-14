<?php declare(strict_types=1);

namespace Shopware\Media\Extension;

use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\Deferred;
use Shopware\Api\Entity\Write\Flag\ReadOnly;
use Shopware\Api\Media\Definition\MediaDefinition;
use Shopware\Api\Media\Event\Media\MediaBasicLoadedEvent;
use Shopware\Media\UrlGeneratorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UrlExtension implements EntityExtensionInterface, EventSubscriberInterface
{
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function extendFields(FieldCollection $collection)
    {
        $collection->add(
            (new StringField('url', 'url'))->setFlags(new Deferred(), new ReadOnly())
        );
    }

    public function getDefinitionClass(): string
    {
        return MediaDefinition::class;
    }

    public static function getSubscribedEvents()
    {
        return [
            MediaBasicLoadedEvent::NAME => 'mediaLoaded',
        ];
    }

    public function mediaLoaded(MediaBasicLoadedEvent $event): void
    {
        foreach ($event->getMedia() as $media) {
            $media->setUrl($this->urlGenerator->getUrl($media->getFileName()));
        }
    }
}
