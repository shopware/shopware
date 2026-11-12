<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Maps the media of the layout overrides of one entity, like the `slotConfig` of categories, to the entity.
 * Every entity has its own indexer.
 *
 * @internal
 */
#[Package('discovery')]
class SlotConfigOverrideMediaUsageIndexer extends AbstractMediaUsageIndexer
{
    /**
     * @param EntityTranslationDefinition $definition translation with layout overrides, like the one of categories
     */
    public function __construct(
        IteratorFactory $iteratorFactory,
        Connection $connection,
        CmsSlotMediaIdResolver $mediaIdResolver,
        private readonly EntityTranslationDefinition $definition,
    ) {
        parent::__construct($iteratorFactory, $connection, $mediaIdResolver);
    }

    public function getName(): string
    {
        return $this->getTableName() . '.indexer';
    }

    /**
     * The translations are iterated by their primary keys, the messages contain the ids of the entities they belong to
     */
    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->createIterator($offset);

        $keys = $iterator->fetch();
        if ($keys === []) {
            return null;
        }

        return new EntityIndexingMessage($this->getOwnerIds(array_values($keys), $this->getOwnerKey()), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $ids = $this->getOwnerIds($event->getPrimaryKeysWithPropertyChange($this->getEntityName(), ['slotConfig']), $this->getOwnerKey());

        return $ids === [] ? null : new EntityIndexingMessage($ids, null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $entity = $this->definition->getParentDefinition()->getEntityName();
        $ids = $this->getIds($message);
        if ($ids === []) {
            return;
        }

        $translations = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT LOWER(HEX(%1$s)) AS version_id, %2$s AS id, language_id, slot_config FROM %3$s WHERE %2$s IN (:ids)',
                EntityDefinitionQueryHelper::escape($entity . '_version_id'),
                EntityDefinitionQueryHelper::escape($entity . '_id'),
                EntityDefinitionQueryHelper::escape($entity . '_translation')
            ),
            ['ids' => $ids],
            ['ids' => ArrayParameterType::BINARY]
        );

        $idsByVersion = FetchModeHelper::group($translations, static fn (array $translation): string => (string) $translation['id']);

        foreach ($translations as $key => $translation) {
            $overrides = json_decode((string) $translation['slot_config'], true);
            if (!\is_array($overrides)) {
                // the usages of translations without overrides are only removed
                unset($translations[$key]);

                continue;
            }

            $translations[$key]['slot_config'] = array_filter($overrides, \is_array(...));
        }

        // the overrides are keyed by the slot ids, the type of the slots decides which resolver knows their media
        $slotTypes = $this->getSlotTypes(array_keys(array_merge(...array_column($translations, 'slot_config'))));

        $rows = [];
        foreach ($translations as $translation) {
            // overrides of slots which do not exist anymore are not shown
            $overrides = array_intersect_key($translation['slot_config'], $slotTypes);

            $mediaIds = array_merge(...array_map(
                fn (string $slotId): array => $this->mediaIdResolver->getMediaIds($slotTypes[$slotId], $overrides[$slotId]),
                array_keys($overrides)
            ));

            foreach ($mediaIds as $mediaId) {
                $rows[] = [
                    $entity . '_id' => (string) $translation['id'],
                    $entity . '_version_id' => Uuid::fromHexToBytes((string) $translation['version_id']),
                    'language_id' => (string) $translation['language_id'],
                    'media_id' => Uuid::fromHexToBytes($mediaId),
                ];
            }
        }

        $this->replaceUsages($idsByVersion, $rows);
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    /**
     * Only few entities override the config of their layout, translations without overrides can not show their media
     */
    protected function createIterator(?array $offset): IterableQuery
    {
        $iterator = parent::createIterator($offset);
        $iterator->getQuery()->andWhere('slot_config IS NOT NULL');

        return $iterator;
    }

    protected function getEntityName(): string
    {
        return $this->definition->getEntityName();
    }

    protected function getOwnerEntityName(): string
    {
        return $this->definition->getParentDefinition()->getEntityName();
    }

    protected function getTableName(): string
    {
        return $this->getEntityName() . '_media_usage';
    }

    /**
     * The property of the translation referencing its entity, like `categoryId`
     */
    private function getOwnerKey(): string
    {
        $reference = $this->definition->getParentDefinition()->getTranslationField()?->getReferenceField();

        return $this->definition->getFields()->getByStorageName((string) $reference)?->getPropertyName() ?? '';
    }

    /**
     * @param list<int|string> $slotIds
     *
     * @return array<string, string> types of the slots by their hex ids
     */
    private function getSlotTypes(array $slotIds): array
    {
        $slotIds = array_values(array_filter($slotIds, static fn (int|string $id): bool => \is_string($id) && Uuid::isValid($id)));
        if ($slotIds === []) {
            return [];
        }

        /** @var array<string, string> */
        return $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)), type FROM cms_slot WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($slotIds)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
