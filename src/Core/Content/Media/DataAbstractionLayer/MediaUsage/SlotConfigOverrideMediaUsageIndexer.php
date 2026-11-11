<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
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
     * @param EntityDefinition $definition entity with layout overrides in its translation, like categories
     */
    public function __construct(
        IteratorFactory $iteratorFactory,
        Connection $connection,
        CmsSlotMediaIdResolver $mediaIdResolver,
        private readonly EntityDefinition $definition,
    ) {
        parent::__construct($iteratorFactory, $connection, $mediaIdResolver);
    }

    public function getName(): string
    {
        return $this->getEntityName() . '_translation_media_usage.indexer';
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $ids = [];
        $translationField = $this->definition->getTranslationField();
        if ($translationField !== null) {
            $translation = $translationField->getReferenceDefinition();
            $ownerField = $translation->getFields()->getByStorageName($translationField->getReferenceField());

            if ($ownerField !== null) {
                $ids = $this->getOwnerIds($event->getPrimaryKeysWithPropertyChange($translation->getEntityName(), ['slotConfig']), $ownerField->getPropertyName());
            }
        }

        return $ids === [] ? null : new EntityIndexingMessage($ids, null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $entity = $this->getEntityName();
        $ids = $this->getIds($message);
        if ($ids === []) {
            return;
        }

        $translations = $this->connection->fetchAllAssociative(
            \sprintf('SELECT `%1$s_id` AS id, `%1$s_version_id` AS version_id, language_id, slot_config FROM `%1$s_translation` WHERE `%1$s_id` IN (:ids)', $entity),
            ['ids' => $ids],
            ['ids' => ArrayParameterType::BINARY]
        );

        $overridesByTranslation = [];
        foreach ($translations as $key => $translation) {
            $overrides = json_decode((string) $translation['slot_config'], true);
            $overridesByTranslation[$key] = \is_array($overrides) ? array_filter($overrides, \is_array(...)) : [];
        }

        // the overrides are keyed by the slot ids, the type of the slots decides which resolver knows their media
        $slotTypes = $this->getSlotTypes(array_keys(array_merge(...array_values($overridesByTranslation) ?: [[]])));

        $rows = [];
        foreach ($translations as $key => $translation) {
            foreach ($overridesByTranslation[$key] as $slotId => $config) {
                foreach ($this->mediaIdResolver->getMediaIds($slotTypes[strtolower((string) $slotId)] ?? '', $config) as $mediaId) {
                    $rows[] = [
                        $entity . '_id' => (string) $translation['id'],
                        $entity . '_version_id' => (string) $translation['version_id'],
                        'language_id' => (string) $translation['language_id'],
                        'media_id' => Uuid::fromHexToBytes($mediaId),
                    ];
                }
            }
        }

        $this->replaceUsages($ids, $rows);
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    /**
     * Only few entities override the config of their layout, the others can not show media of overrides.
     * The join returns an entity once per language with overrides, distinct returns it once.
     */
    protected function createIterator(?array $offset): IterableQuery
    {
        $entity = $this->getEntityName();
        $table = EntityDefinitionQueryHelper::escape($entity);
        $iterator = parent::createIterator($offset);

        $iterator->getQuery()
            ->distinct()
            ->innerJoin(
                $table,
                EntityDefinitionQueryHelper::escape($entity . '_translation'),
                'translation',
                \sprintf(
                    'translation.%s = %s.id AND translation.%s = %s.version_id',
                    EntityDefinitionQueryHelper::escape($entity . '_id'),
                    $table,
                    EntityDefinitionQueryHelper::escape($entity . '_version_id'),
                    $table,
                )
            )
            ->andWhere('translation.slot_config IS NOT NULL');

        return $iterator;
    }

    protected function getEntityName(): string
    {
        return $this->definition->getEntityName();
    }

    protected function getTableName(): string
    {
        return $this->getEntityName() . '_translation_media_usage';
    }

    /**
     * @param list<int|string> $slotIds
     *
     * @return array<string, string> types of the slots by their hex ids
     */
    private function getSlotTypes(array $slotIds): array
    {
        $slotIds = array_values(array_filter(array_map(static fn (int|string $id) => strtolower((string) $id), $slotIds), Uuid::isValid(...)));
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
