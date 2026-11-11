<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage;

use Doctrine\DBAL\ArrayParameterType;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Maps the section and block backgrounds and the media of the CMS elements to the layout showing them,
 * whose cache tag is invalidated when a media is replaced.
 * Sections, blocks and slots deleted on their own can not be traced to their layout anymore,
 * their media stay mapped until the layout is indexed again, which only invalidates the layout once too often.
 *
 * @internal
 */
#[Package('discovery')]
class CmsPageMediaUsageIndexer extends AbstractMediaUsageIndexer
{
    public function getName(): string
    {
        return 'cms_page_media_usage.indexer';
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $sectionIds = $this->getOwnerIds($event->getPrimaryKeysWithPropertyChange(CmsSectionDefinition::ENTITY_NAME, ['backgroundMediaId']), 'id');
        $blockIds = $this->getOwnerIds($event->getPrimaryKeysWithPropertyChange(CmsBlockDefinition::ENTITY_NAME, ['backgroundMediaId']), 'id');
        $slotIds = $this->getOwnerIds($event->getPrimaryKeysWithPropertyChange(CmsSlotTranslationDefinition::ENTITY_NAME, ['config']), 'cmsSlotId');

        $ids = array_values(array_unique([
            ...$this->getOwnerIds($event->getPrimaryKeys(CmsPageDefinition::ENTITY_NAME), 'id'),
            ...$this->getPageIds('SELECT LOWER(HEX(cms_page_id)) FROM cms_section WHERE id IN (:ids)', $sectionIds),
            ...$this->getPageIds(
                'SELECT LOWER(HEX(section.cms_page_id))
                 FROM cms_block AS block
                 INNER JOIN cms_section AS section ON section.id = block.cms_section_id AND section.version_id = block.cms_section_version_id
                 WHERE block.id IN (:ids)',
                $blockIds
            ),
            ...$this->getPageIds(
                'SELECT LOWER(HEX(section.cms_page_id))
                 FROM cms_slot AS slot
                 INNER JOIN cms_block AS block ON block.id = slot.cms_block_id AND block.version_id = slot.cms_block_version_id
                 INNER JOIN cms_section AS section ON section.id = block.cms_section_id AND section.version_id = block.cms_section_version_id
                 WHERE slot.id IN (:ids)',
                $slotIds
            ),
        ]));

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

        $backgrounds = [
            ...$this->connection->fetchAllAssociative(
                'SELECT cms_page_id, cms_page_version_id, background_media_id AS media_id
                 FROM cms_section
                 WHERE cms_page_id IN (:ids) AND background_media_id IS NOT NULL',
                $parameters,
                $types
            ),
            ...$this->connection->fetchAllAssociative(
                'SELECT section.cms_page_id, section.cms_page_version_id, block.background_media_id AS media_id
                 FROM cms_block AS block
                 INNER JOIN cms_section AS section ON section.id = block.cms_section_id AND section.version_id = block.cms_section_version_id
                 WHERE section.cms_page_id IN (:ids) AND block.background_media_id IS NOT NULL',
                $parameters,
                $types
            ),
        ];

        $slots = $this->connection->fetchAllAssociative(
            'SELECT section.cms_page_id, section.cms_page_version_id, slot.type, translation.config
             FROM cms_slot_translation AS translation
             INNER JOIN cms_slot AS slot ON slot.id = translation.cms_slot_id AND slot.version_id = translation.cms_slot_version_id
             INNER JOIN cms_block AS block ON block.id = slot.cms_block_id AND block.version_id = slot.cms_block_version_id
             INNER JOIN cms_section AS section ON section.id = block.cms_section_id AND section.version_id = block.cms_section_version_id
             WHERE section.cms_page_id IN (:ids)',
            $parameters,
            $types
        );

        $rows = [];
        foreach ($backgrounds as $background) {
            $rows[] = self::row($background, (string) $background['media_id']);
        }

        foreach ($slots as $slot) {
            $config = json_decode((string) $slot['config'], true) ?? [];

            foreach ($this->mediaIdResolver->getMediaIds((string) $slot['type'], \is_array($config) ? $config : []) as $mediaId) {
                $rows[] = self::row($slot, Uuid::fromHexToBytes($mediaId));
            }
        }

        $this->replaceUsages($ids, $rows);
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    protected function getEntityName(): string
    {
        return CmsPageDefinition::ENTITY_NAME;
    }

    protected function getTableName(): string
    {
        return 'cms_page_media_usage';
    }

    /**
     * @param array<string, mixed> $page
     *
     * @return array<string, string>
     */
    private static function row(array $page, string $mediaId): array
    {
        return [
            'cms_page_id' => (string) $page['cms_page_id'],
            'cms_page_version_id' => (string) $page['cms_page_version_id'],
            'media_id' => $mediaId,
        ];
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function getPageIds(string $sql, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->connection->fetchFirstColumn($sql, ['ids' => Uuid::fromHexToBytesList($ids)], ['ids' => ArrayParameterType::BINARY]);
    }
}
