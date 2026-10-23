<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Fills the runtime `media` association of product documents. The file is stored per language in the translation,
 * so it is loaded after the documents, for the language chain they were read in.
 *
 * @internal
 */
#[Package('inventory')]
class ProductDocumentMediaSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(private readonly EntityRepository $mediaRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductDocumentDefinition::ENTITY_NAME . '.loaded' => 'resolveMedia',
        ];
    }

    /**
     * @param EntityLoadedEvent<ProductDocumentEntity> $event
     */
    public function resolveMedia(EntityLoadedEvent $event): void
    {
        $mediaIds = [];
        foreach ($event->getEntities() as $document) {
            if ($document->getMedia() !== null) {
                continue;
            }

            $mediaId = $document->getTranslation('mediaId') ?? $document->getMediaId();
            if (\is_string($mediaId)) {
                $mediaIds[$document->getUniqueIdentifier()] = $mediaId;
            }
        }

        if ($mediaIds === []) {
            return;
        }

        // The documents themselves were loaded through an access-checked read, so their files are read in system scope.
        $media = $event->getContext()->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $context): MediaCollection => $this->mediaRepository
                ->search(new Criteria(array_values(array_unique($mediaIds))), $context)
                ->getEntities()
        );

        foreach ($event->getEntities() as $document) {
            $mediaId = $mediaIds[$document->getUniqueIdentifier()] ?? null;
            $file = $mediaId !== null ? $media->get($mediaId) : null;

            if ($file !== null) {
                $document->setMedia($file);
            }
        }
    }
}
