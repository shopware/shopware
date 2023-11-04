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
class Migration1611732852UpdateCmsPdpLayout extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1611732852;
    }

    public function update(Connection $connection): void
    {
        $this->updateDefaultPdpLayout($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function updateDefaultPdpLayout(Connection $connection): void
    {
        $sectionId = $connection->fetchOne('
            SELECT id
            FROM cms_section
            WHERE cms_page_id = :cmsPageId
        ', ['cmsPageId' => Uuid::fromHexToBytes(Defaults::CMS_PRODUCT_DETAIL_PAGE)]);

        $blockIds = $connection->fetchAllAssociative('
            SELECT id
            FROM cms_block
            WHERE cms_section_id = :cmsSectionId
        ', ['cmsSectionId' => $sectionId]);

        $blockIds = array_column($blockIds, 'id');

        $slots = $connection->fetchAllAssociative('
            SELECT id, type
            FROM cms_slot
            WHERE cms_block_id IN (:cmsBlockId)
        ', ['cmsBlockId' => $blockIds], ['cmsBlockId' => ArrayParameterType::STRING]);

        foreach ($slots as $slot) {
            $configData = match ($slot['type']) {
                'product-name' => [
                    'content' => ['value' => 'product.name', 'source' => 'mapped'],
                    'verticalAlign' => ['value' => null, 'source' => 'static'],
                ],
                'manufacturer-logo' => [
                    'displayMode' => ['source' => 'static', 'value' => 'cover'],
                    'media' => ['value' => 'product.manufacturer.media', 'source' => 'mapped'],
                    'minHeight' => ['value' => null, 'source' => 'static'],
                    'newTab' => ['value' => true, 'source' => 'static'],
                    'url' => ['value' => null, 'source' => 'static'],
                    'verticalAlign' => ['value' => null, 'source' => 'static'],
                ],
                'image-gallery' => [
                    'displayMode' => ['value' => 'standard', 'source' => 'static'],
                    'fullScreen' => ['value' => false, 'source' => 'static'],
                    'galleryPosition' => ['value' => 'left', 'source' => 'static'],
                    'minHeight' => ['value' => '430px', 'source' => 'static'],
                    'navigationArrows' => ['value' => 'inside', 'source' => 'static'],
                    'navigationDots' => ['value' => null, 'source' => 'static'],
                    'sliderItems' => ['value' => 'product.media', 'source' => 'mapped'],
                    'verticalAlign' => ['value' => null, 'source' => 'static'],
                    'zoom' => ['value' => false, 'source' => 'static'],
                ],
                'buy-box', 'product-description-reviews' => [
                    'product' => ['value' => null, 'source' => 'static'],
                    'alignment' => ['value' => null, 'source' => 'static'],
                ],
                'cross-selling' => [
                    'boxLayout' => ['source' => 'static', 'value' => 'standard'],
                    'displayMode' => ['source' => 'static', 'value' => 'standard'],
                    'elMinWidth' => ['value' => '200px', 'source' => 'static'],
                    'product' => ['value' => null, 'source' => 'static'],
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
