<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Gives each document loaded for a sales channel its file in the shopper's language,
 * or in the language it falls back to.
 *
 * @internal
 */
#[Package('inventory')]
final class ProductDocumentMediaSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     * @param EntityRepository<ProductDocumentTranslationCollection> $productDocumentTranslationRepository
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository,
        private readonly EntityRepository $productDocumentTranslationRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return ['sales_channel.' . ProductDocumentDefinition::ENTITY_NAME . '.loaded' => 'addMedia'];
    }

    /**
     * @param SalesChannelEntityLoadedEvent<ProductDocumentEntity> $event
     */
    public function addMedia(SalesChannelEntityLoadedEvent $event): void
    {
        $documentsWithFile = [];
        foreach ($event->getEntities() as $document) {
            $mediaId = $document->getTranslation('mediaId');
            if (\is_string($mediaId)) {
                $documentsWithFile[] = [$document, $mediaId];
            }
        }

        if ($documentsWithFile === []) {
            return;
        }

        $context = $event->getContext();
        $files = $this->mediaRepository->search(new Criteria(array_values(array_unique(array_column($documentsWithFile, 1)))), $context)->getEntities();
        $fileLanguages = $this->getFileLanguages(array_column($documentsWithFile, 0), $context);

        foreach ($documentsWithFile as [$document, $mediaId]) {
            $media = $files->get($mediaId);
            if ($media !== null) {
                $document->setMedia($media);
                $document->setMediaLanguageId($fileLanguages[$document->getId()]);
            }
        }
    }

    /**
     * A document's own file is in the shopper's language. Otherwise the file comes from the parent language,
     * if the shopper's language has one with a file, or else from the default language.
     *
     * @param list<ProductDocumentEntity> $documents
     *
     * @return array<string, string> language id per document id
     */
    private function getFileLanguages(array $documents, Context $context): array
    {
        $languageIds = $context->getLanguageIdChain();
        $defaultLanguageId = $languageIds[\count($languageIds) - 1];

        $fileLanguages = [];
        $fallbackDocumentIds = [];
        foreach ($documents as $document) {
            if ($document->getMediaId() !== null) {
                $fileLanguages[$document->getId()] = $languageIds[0];

                continue;
            }

            $fileLanguages[$document->getId()] = $defaultLanguageId;
            $fallbackDocumentIds[] = $document->getId();
        }

        // only a language with a parent has a third language in its chain
        if (\count($languageIds) < 3 || $fallbackDocumentIds === []) {
            return $fileLanguages;
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsAnyFilter('productDocumentId', array_values(array_unique($fallbackDocumentIds))),
            new EqualsFilter('productDocumentVersionId', $context->getVersionId()),
            new EqualsFilter('languageId', $languageIds[1]),
        );

        foreach ($this->productDocumentTranslationRepository->search($criteria, $context)->getEntities() as $translation) {
            if ($translation->getMediaId() !== null) {
                $fileLanguages[$translation->getProductDocumentId()] = $languageIds[1];
            }
        }

        return $fileLanguages;
    }
}
