<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Document\ProductDocumentArchiver;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentArchiveSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly FilesystemOperator $privateFilesystem,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'invalidateWrittenDocuments',
            EntityDeleteEvent::class => 'invalidateDeletedDocuments',
            MediaIndexerEvent::class => 'invalidateIndexedFiles',
            ProductEvents::PRODUCT_DELETED_EVENT => 'invalidateDeletedProducts',
            InvalidateCacheEvent::class => 'deleteArchives',
        ];
    }

    public function invalidateWrittenDocuments(EntityWrittenContainerEvent $event): void
    {
        // a ZIP only depends on the files and their order
        $this->invalidate($this->fetchProductIds([
            ...$event->getPrimaryKeysWithPropertyChange(ProductDocumentDefinition::ENTITY_NAME, ['position']),
            ...$event->getPrimaryKeysWithPropertyChange(ProductDocumentTranslationDefinition::ENTITY_NAME, ['mediaId']),
        ]));
    }

    public function invalidateDeletedDocuments(EntityDeleteEvent $event): void
    {
        // the documents are gone after the delete, so their products are read before
        $productIds = $this->fetchProductIds([
            ...$event->getIds(ProductDocumentDefinition::ENTITY_NAME),
            ...$event->getIds(ProductDocumentTranslationDefinition::ENTITY_NAME),
        ]);

        if ($productIds === []) {
            return;
        }

        $event->addSuccess(fn () => $this->invalidate($productIds));
    }

    public function invalidateIndexedFiles(MediaIndexerEvent $event): void
    {
        /** @var list<string> $productIds */
        $productIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(pd.product_id))
                FROM product_document_translation AS pdt
                INNER JOIN product_document AS pd ON (pdt.product_document_id = pd.id AND pdt.product_document_version_id = pd.version_id)
                WHERE pdt.media_id IN (:ids) AND pd.version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($event->getIds()), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $this->invalidate($productIds);
    }

    public function invalidateDeletedProducts(EntityDeletedEvent $event): void
    {
        $this->invalidate($this->extractIds($event->getIds()));
    }

    public function deleteArchives(InvalidateCacheEvent $event): void
    {
        foreach ($event->getKeys() as $tag) {
            $productId = ProductDocumentArchiver::extractProductId($tag);
            if ($productId === null) {
                continue;
            }

            try {
                $this->privateFilesystem->deleteDirectory(ProductDocumentArchiver::DIRECTORY . '/' . $productId);
            } catch (FilesystemException $exception) {
                $this->logger->warning('The stored document archives of a product could not be deleted.', [
                    'productId' => $productId,
                    'exception' => $exception,
                ]);
            }
        }
    }

    /**
     * @param array<mixed> $primaryKeys
     *
     * @return list<string>
     */
    private function fetchProductIds(array $primaryKeys): array
    {
        $documentIds = $this->extractIds($primaryKeys);
        if ($documentIds === []) {
            return [];
        }

        /** @var list<string> $productIds */
        $productIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(product_id)) FROM product_document WHERE id IN (:ids) AND version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($documentIds), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $productIds;
    }

    /**
     * @param array<mixed> $primaryKeys
     *
     * @return list<string>
     */
    private function extractIds(array $primaryKeys): array
    {
        $ids = [];
        foreach ($primaryKeys as $primaryKey) {
            $id = \is_array($primaryKey) ? ($primaryKey['productDocumentId'] ?? $primaryKey['id'] ?? null) : $primaryKey;
            if (\is_string($id) && Uuid::isValid($id)) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * @param list<string> $productIds
     */
    private function invalidate(array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        $this->cacheInvalidator->invalidate(array_map(ProductDocumentArchiver::buildTag(...), array_values(array_unique($productIds))));
    }
}
