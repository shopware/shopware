<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
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
 * Gives each document loaded for a sales channel its file in the requested language,
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
     * Loads the public files the DAL resolved for the documents (`translated.mediaId`) in one search,
     * and adds each file with its language.
     *
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

        $criteria = new Criteria(array_values(array_unique(array_column($documentsWithFile, 1))));
        // media searches still return the private files of product downloads
        $criteria->addFilter(new EqualsFilter('private', false));

        $context = $event->getContext();
        $files = $this->mediaRepository->search($criteria, $context)->getEntities();
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
     * The DAL takes a document's file from the first language of the context's language chain that has one.
     * The chain is: the requested language, then its parent language if it has one, then the default language.
     * This returns which of them it was, per document.
     *
     * @param list<ProductDocumentEntity> $documents
     *
     * @return array<string, string> language id per document id
     */
    private function getFileLanguages(array $documents, Context $context): array
    {
        // a language that inherits from the default language has it twice in its chain
        $languageIds = array_values(array_unique($context->getLanguageIdChain()));
        $defaultLanguageId = $languageIds[\count($languageIds) - 1];

        $fileLanguages = [];
        $fallbackDocumentIds = [];
        foreach ($documents as $document) {
            // `mediaId` is the requested language's own file, `translated.mediaId` the resolved one
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
