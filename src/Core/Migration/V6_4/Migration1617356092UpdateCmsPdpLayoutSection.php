<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1617356092UpdateCmsPdpLayoutSection extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1617356092;
    }

    public function update(Connection $connection): void
    {
        $this->updateDefaultPdpLayout($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function updateDefaultPdpLayout(Connection $connection): void
    {
        $sectionId = $connection->fetchOne('
            SELECT id
            FROM cms_section
            WHERE cms_page_id = :cmsPageId
        ', ['cmsPageId' => Uuid::fromHexToBytes(Defaults::CMS_PRODUCT_DETAIL_PAGE)]);

        $blocks = $connection->fetchAllAssociative('
            SELECT id, type
            FROM cms_block
            WHERE cms_section_id = :cmsSectionId
        ', ['cmsSectionId' => $sectionId]);

        $blockIds = array_column($blocks, 'id');

        foreach ($blocks as $block) {
            switch ($block['type']) {
                case 'product-heading':
                    $marginTop = '0';
                    $marginBottom = '20px';

                    break;

                case 'gallery-buybox':
                    $marginTop = '20px';
                    $marginBottom = '0';

                    break;

                case 'cross-selling':
                    $marginTop = '0';
                    $marginBottom = '0';

                    break;

                default:
                    $marginTop = '20px';
                    $marginBottom = '20px';
            }

            $connection->executeStatement('
                UPDATE cms_block
                SET margin_left = :marginLeft,
                    margin_right = :marginRight,
                    margin_top = :marginTop,
                    margin_bottom = :marginBottom
                WHERE id = :blockId
            ', [
                'blockId' => $block['id'],
                'marginLeft' => '0',
                'marginRight' => '0',
                'marginTop' => $marginTop,
                'marginBottom' => $marginBottom,
            ]);
        }

        $slots = $connection->fetchAllAssociative('
            SELECT id, `type`
            FROM cms_slot
            WHERE cms_block_id IN (:cmsBlockId)
            AND type = "image-gallery" OR `type` = "manufacturer-logo"
        ', ['cmsBlockId' => $blockIds], ['cmsBlockId' => ArrayParameterType::STRING]);

        foreach ($slots as $slot) {
            $configData = match ($slot['type']) {
                'manufacturer-logo' => [
                    'displayMode' => ['source' => 'static', 'value' => 'standard'],
                    'media' => ['value' => 'product.manufacturer.media', 'source' => 'mapped'],
                    'minHeight' => ['value' => null, 'source' => 'static'],
                    'newTab' => ['value' => true, 'source' => 'static'],
                    'url' => ['value' => null, 'source' => 'static'],
                    'verticalAlign' => ['value' => null, 'source' => 'static'],
                ],
                'image-gallery' => [
                    'displayMode' => ['value' => 'contain', 'source' => 'static'],
                    'fullScreen' => ['value' => true, 'source' => 'static'],
                    'galleryPosition' => ['value' => 'left', 'source' => 'static'],
                    'minHeight' => ['value' => '430px', 'source' => 'static'],
                    'navigationArrows' => ['value' => 'inside', 'source' => 'static'],
                    'navigationDots' => ['value' => 'inside', 'source' => 'static'],
                    'sliderItems' => ['value' => 'product.media', 'source' => 'mapped'],
                    'verticalAlign' => ['value' => null, 'source' => 'static'],
                    'zoom' => ['value' => true, 'source' => 'static'],
                ],
                default => [],
            };

            if (empty($configData)) {
                return;
            }

            $connection->executeStatement('
                UPDATE cms_slot_translation
                SET config = :config
                WHERE cms_slot_id = :slotId
            ', [
                'slotId' => $slot['id'],
                'config' => json_encode($configData, \JSON_THROW_ON_ERROR),
            ]);
        }
    }
}
