<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\Events\ProductCreatedEvent;
use Shopware\Core\Content\Product\Events\ProductDeletedEvent;
use Shopware\Core\Content\Product\Events\ProductPublishedEvent;
use Shopware\Core\Content\Product\Events\ProductStockChangedEvent;
use Shopware\Core\Content\Product\Events\ProductUnpublishedEvent;
use Shopware\Core\Content\Product\Events\ProductUpdatedEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Dispatches the inventory.product.* lifecycle events plus product.published /
 * product.unpublished / product.stock.changed for every DAL producer of product writes
 * (admin, sync, import, version merge). Live-version writes only. Entity payloads are
 * loaded lazily — only when a webhook with a matching event name encodes the payload.
 *
 * Why a DAL subscriber and not a domain dispatch: a product has no creation/update
 * service to hook — products are written directly through the DAL by the Admin API, the
 * Sync API and imports, with no shared chokepoint. Reacting to the entity write is the
 * only way to fire for every producer; the alternative (dispatching from one creation
 * path) would silently miss the others, the exact gap these events exist to close. The
 * order-driven stock moment, which does have a chokepoint, is dispatched from
 * StockStorage instead — only the entity-lifecycle mirrors live here.
 *
 * @todo If a product domain service (e.g. a publish/create/update service) is ever
 *       introduced, move the corresponding dispatch into that service so the business
 *       moment is stated at the domain action rather than reconstructed from the write.
 *
 * @internal
 */
#[Package('inventory')]
class ProductBusinessEventSubscriber implements EventSubscriberInterface
{
    private const IGNORED_PRODUCT_FIELDS = [
        'id',
        'versionId',
        'createdAt',
        'updatedAt',
        // write-protected mirror of `stock`, appended by AvailableStockMirrorSubscriber
        // on every stock write — never a caller-written field
        'availableStock',
    ];

    private const IGNORED_TRANSLATION_FIELDS = [
        'productId',
        'productVersionId',
        'languageId',
        'createdAt',
        'updatedAt',
    ];

    /**
     * @param EntityRepository<ProductCollection> $productRepository
     *
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $productRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'beforeWrite',
            EntityDeleteEvent::class => 'beforeDelete',
            EntityWrittenContainerEvent::class => 'onEntityWritten',
        ];
    }

    /**
     * Captures the pre-write `active` flags so that product.published/unpublished fire
     * only on an actual flip — not on every write that happens to contain `active`.
     * Same idiom as OrderStockSubscriber::beforeWriteOrderItems.
     */
    public function beforeWrite(EntityWriteEvent $event): void
    {
        $context = $event->getContext();
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $activeByProduct = [];
        $deletedProductIds = [];
        foreach ($event->getCommandsForEntity(ProductDefinition::ENTITY_NAME) as $command) {
            if ($command instanceof DeleteCommand) {
                $deletedId = $command->getDecodedPrimaryKey()['id'] ?? null;
                if (\is_string($deletedId)) {
                    $deletedProductIds[$deletedId] = true;
                }

                continue;
            }

            if (!$command instanceof UpdateCommand) {
                continue;
            }

            $payload = $command->getPayload();
            if (!\array_key_exists('active', $payload) || $payload['active'] === null) {
                continue;
            }

            // decoded primary keys are property-named with version fields stripped
            // (WriteCommand::setDecodedPrimaryKey); the live-version guard is the
            // context check above
            $productId = $command->getDecodedPrimaryKey()['id'] ?? null;
            if (!\is_string($productId)) {
                continue;
            }

            $activeByProduct[$productId] = (bool) $payload['active'];
        }

        if ($activeByProduct === []) {
            return;
        }

        $activeBefore = $this->fetchActiveFlags(array_keys($activeByProduct));

        $event->addSuccess(function () use ($activeByProduct, $activeBefore, $deletedProductIds, $context): void {
            foreach ($activeByProduct as $productId => $isActive) {
                // a product whose active flag changed but which is deleted in the same
                // write (sync/version merge) must not emit a publish event — the row is
                // gone and the lazy loader would throw productNotFound on webhook encode
                if (isset($deletedProductIds[$productId])) {
                    continue;
                }

                $wasActive = $activeBefore[$productId] ?? null;
                if ($wasActive === null || $wasActive === $isActive) {
                    continue;
                }

                $this->eventDispatcher->dispatch($isActive
                    ? new ProductPublishedEvent($context, $productId, $this->createProductLoader($productId, $context))
                    : new ProductUnpublishedEvent($context, $productId, $this->createProductLoader($productId, $context)));
            }
        });
    }

    public function beforeDelete(EntityDeleteEvent $event): void
    {
        $context = $event->getContext();
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        // product has a single primary key, so getIds yields a list of id strings;
        // the live-version guard is the context check above
        $productIds = array_values(array_filter(
            $event->getIds(ProductDefinition::ENTITY_NAME),
            '\is_string'
        ));

        if ($productIds === []) {
            return;
        }

        $products = $this->productRepository->search(new Criteria($productIds), $context)->getEntities();
        if ($products->count() === 0) {
            return;
        }

        // dispatched from addSuccess (after the delete operation) and snapshotted here
        // because the row — and its productNumber — is gone afterwards. This is the
        // platform's delete-event contract: CustomerBeforeDeleteSubscriber dispatches
        // CustomerDeletedEvent the same way. Like that event, the deleted product carries
        // only scalars (no EntityType), so it is not gated by the product read ACL —
        // consistent with CustomerDeletedEvent exposing the customer ungated.
        $event->addSuccess(function () use ($products, $context): void {
            $deletedAt = (new \DateTimeImmutable())->format(\DATE_ATOM);

            foreach ($products as $product) {
                $this->eventDispatcher->dispatch(new ProductDeletedEvent(
                    $context,
                    $product->getId(),
                    $deletedAt,
                    $product->getProductNumber()
                ));
            }
        });
    }

    public function onEntityWritten(EntityWrittenContainerEvent $event): void
    {
        $context = $event->getContext();
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $createdIds = [];
        $deletedIds = [];
        $changedFieldsByProduct = [];
        $stockByProduct = [];

        $writtenEvent = $event->getEventByEntityName(ProductDefinition::ENTITY_NAME);
        if ($writtenEvent !== null) {
            foreach ($writtenEvent->getWriteResults() as $writeResult) {
                $productId = $this->getLiveProductId($writeResult);
                if ($productId === null) {
                    continue;
                }

                $payload = $writeResult->getPayload();

                if ($writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                    $createdIds[$productId] = true;
                    $this->eventDispatcher->dispatch(new ProductCreatedEvent(
                        $context,
                        $productId,
                        $this->createRichProductLoader($productId, $context)
                    ));

                    continue;
                }

                if ($writeResult->getOperation() === EntityWriteResult::OPERATION_DELETE) {
                    $deletedIds[$productId] = true;

                    continue;
                }

                if ($writeResult->getOperation() !== EntityWriteResult::OPERATION_UPDATE) {
                    continue;
                }

                // Scope: only the product's own (and translated) fields are reported.
                // Relation-only writes — categories, visibilities, prices — arrive as a
                // product update result with an empty payload and intentionally do NOT
                // fire inventory.product.updated; those relations are deferred to their
                // own dedicated events (catalog Part 2 §2.6/§2.7, Part 1 §1.8). This is
                // unlike order aggregates, which are mapped onto checkout.order.updated.
                $changedFields = array_values(array_diff(array_keys($payload), self::IGNORED_PRODUCT_FIELDS));
                if ($changedFields !== []) {
                    $changedFieldsByProduct[$productId] = [...($changedFieldsByProduct[$productId] ?? []), ...$changedFields];
                }

                $stock = $payload['stock'] ?? null;
                if (\is_int($stock)) {
                    $stockByProduct[$productId] = $stock;
                }
            }
        }

        $translationEvent = $event->getEventByEntityName(ProductTranslationDefinition::ENTITY_NAME);
        if ($translationEvent !== null) {
            foreach ($translationEvent->getWriteResults() as $writeResult) {
                $productId = $this->getLiveTranslationProductId($writeResult);
                // a translation written alongside a product insert is already covered by
                // product.created; a translation removed because its product was deleted is
                // covered by product.deleted — and the product row is gone, so the rich
                // loader would throw productNotFound when a webhook encodes the event
                if ($productId === null || isset($createdIds[$productId]) || isset($deletedIds[$productId])) {
                    continue;
                }

                if ($writeResult->getOperation() === EntityWriteResult::OPERATION_DELETE) {
                    // a localized row was removed while the product survives — localized
                    // data changed, so surface it as a product update. The delete result
                    // carries only the primary key, so report at translation granularity.
                    $changedFieldsByProduct[$productId] = [...($changedFieldsByProduct[$productId] ?? []), 'translation'];

                    continue;
                }

                $changedFields = array_map(
                    static fn (string $field): string => 'translation.' . $field,
                    array_values(array_diff(array_keys($writeResult->getPayload()), self::IGNORED_TRANSLATION_FIELDS))
                );
                if ($changedFields !== []) {
                    $changedFieldsByProduct[$productId] = [...($changedFieldsByProduct[$productId] ?? []), ...$changedFields];
                }
            }
        }

        foreach ($changedFieldsByProduct as $productId => $changedFields) {
            $this->eventDispatcher->dispatch(new ProductUpdatedEvent(
                $context,
                $productId,
                $this->createRichProductLoader($productId, $context),
                array_values(array_unique($changedFields))
            ));
        }

        foreach ($stockByProduct as $productId => $stock) {
            $this->eventDispatcher->dispatch(new ProductStockChangedEvent(
                $context,
                $productId,
                $this->createProductLoader($productId, $context),
                $stock
            ));
        }
    }

    private function getLiveProductId(EntityWriteResult $writeResult): ?string
    {
        $primaryKey = $writeResult->getPrimaryKey();
        $versionId = $this->getStringValue($writeResult->getPayload(), 'versionId');

        if (\is_array($primaryKey)) {
            $id = $this->getStringValue($primaryKey, 'id');
            $versionId ??= $this->getStringValue($primaryKey, 'versionId');
        } else {
            $id = $primaryKey;
        }

        if ($id === null) {
            return null;
        }

        if ($versionId !== null && $versionId !== Defaults::LIVE_VERSION) {
            return null;
        }

        return $id;
    }

    private function getLiveTranslationProductId(EntityWriteResult $writeResult): ?string
    {
        $primaryKey = $writeResult->getPrimaryKey();
        $payload = $writeResult->getPayload();
        $versionId = $this->getStringValue($payload, 'productVersionId');

        if (\is_array($primaryKey)) {
            $productId = $this->getStringValue($primaryKey, 'productId');
            $versionId ??= $this->getStringValue($primaryKey, 'productVersionId');
        } else {
            $productId = $this->getStringValue($payload, 'productId');
        }

        if ($productId === null) {
            return null;
        }

        if ($versionId !== null && $versionId !== Defaults::LIVE_VERSION) {
            return null;
        }

        return $productId;
    }

    /**
     * @param list<string> $productIds
     *
     * @return array<string, bool|null>
     */
    private function fetchActiveFlags(array $productIds): array
    {
        $rows = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)), active FROM product WHERE id IN (:ids) AND version_id = :version',
            [
                'ids' => Uuid::fromHexToBytesList($productIds),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );

        $flags = [];
        foreach ($rows as $id => $active) {
            $flags[(string) $id] = $active === null ? null : (bool) $active;
        }

        return $flags;
    }

    /**
     * @return \Closure(): ProductEntity
     */
    private function createProductLoader(string $productId, Context $context): \Closure
    {
        return $this->createLoader(new Criteria([$productId]), $productId, $context);
    }

    /**
     * @return \Closure(): ProductEntity
     */
    private function createRichProductLoader(string $productId, Context $context): \Closure
    {
        $criteria = (new Criteria([$productId]))
            ->addAssociation('visibilities')
            ->addAssociation('categories')
            ->addAssociation('cover');

        return $this->createLoader($criteria, $productId, $context);
    }

    /**
     * @return \Closure(): ProductEntity
     */
    private function createLoader(Criteria $criteria, string $productId, Context $context): \Closure
    {
        return function () use ($criteria, $productId, $context): ProductEntity {
            $product = $this->productRepository->search($criteria, $context)->getEntities()->get($productId);
            if (!$product instanceof ProductEntity) {
                throw ProductException::productNotFound($productId);
            }

            return $product;
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function getStringValue(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return \is_string($value) ? $value : null;
    }
}
