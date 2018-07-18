<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Extension;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\Util\UrlGeneratorInterface;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Event\EntityLoadedEvent;
use Shopware\Core\Framework\ORM\Field\StructField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\Extension;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UrlExtension implements EventSubscriberInterface, EntityExtensionInterface
{
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new StructField('links', MediaLinksStruct::class, true))->addFlags(new Extension())
        );
    }

    public function getDefinitionClass(): string
    {
        return MediaDefinition::class;
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
            if (!$media->getMimeType()) {
                continue;
            }

            $url = $this->urlGenerator->getUrl($media->getId(), $media->getMimeType());

            $media->addExtension('links', new MediaLinksStruct($url));
        }
    }
}
