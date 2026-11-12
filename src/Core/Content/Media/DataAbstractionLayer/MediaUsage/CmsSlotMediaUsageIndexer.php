<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage;

use Doctrine\DBAL\ArrayParameterType;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Maps the media of the CMS elements to their slot and the layout showing them,
 * whose cache tag is invalidated when a media is replaced.
 * Deleting a slot, or the block or section containing it, removes its rows by the foreign key to the slot.
 * Section and block backgrounds are foreign keys of their own and are not mapped.
 *
 * @internal
 */
#[Package('discovery')]
class CmsSlotMediaUsageIndexer extends AbstractMediaUsageIndexer
{
    public function getName(): string
    {
        return 'cms_page_media_usage.indexer';
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $ids = $this->getOwnerIds($event->getPrimaryKeysWithPropertyChange(CmsSlotTranslationDefinition::ENTITY_NAME, ['config']), 'cmsSlotId');

        return $ids === [] ? null : new EntityIndexingMessage($ids, null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $this->getIds($message);
        if ($ids === []) {
            return;
        }
        $parameters = ['ids' => $ids];
        $types = ['ids' => ArrayParameterType::BINARY];

        $slots = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(slot.version_id)) AS cms_slot_version_id, section.cms_page_id, section.cms_page_version_id, slot.id AS cms_slot_id, slot.type, translation.config
             FROM cms_slot_translation AS translation
             INNER JOIN cms_slot AS slot ON slot.id = translation.cms_slot_id AND slot.version_id = translation.cms_slot_version_id
             INNER JOIN cms_block AS block ON block.id = slot.cms_block_id AND block.version_id = slot.cms_block_version_id
             INNER JOIN cms_section AS section ON section.id = block.cms_section_id AND section.version_id = block.cms_section_version_id
             WHERE slot.id IN (:ids)',
            $parameters,
            $types
        );

        $rows = [];
        foreach ($slots as $slot) {
            $config = json_decode((string) $slot['config'], true) ?? [];

            foreach ($this->mediaIdResolver->getMediaIds((string) $slot['type'], \is_array($config) ? $config : []) as $mediaId) {
                $rows[] = $this->row($slot, Uuid::fromHexToBytes($mediaId));
            }
        }

        $this->replaceUsages(FetchModeHelper::group($slots, static fn (array $slot): string => (string) $slot['cms_slot_id']), $rows);
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    protected function getEntityName(): string
    {
        return CmsSlotDefinition::ENTITY_NAME;
    }

    protected function getTableName(): string
    {
        return 'cms_page_media_usage';
    }

    /**
     * @param array<string, mixed> $slot
     *
     * @return array<string, string>
     */
    private function row(array $slot, string $mediaId): array
    {
        return [
            'cms_page_id' => (string) $slot['cms_page_id'],
            'cms_page_version_id' => (string) $slot['cms_page_version_id'],
            'cms_slot_id' => (string) $slot['cms_slot_id'],
            'cms_slot_version_id' => Uuid::fromHexToBytes((string) $slot['cms_slot_version_id']),
            'media_id' => $mediaId,
        ];
    }
}
