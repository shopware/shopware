<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

/**
 * @internal
 */
#[Package('core')]
class Migration1609140710AddCmsPdpLayout extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1609140710;
    }

    public function update(Connection $connection): void
    {
        $this->addDefaultPdpLayout($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addDefaultPdpLayout(Connection $connection): void
    {
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        // cms page
        $page = [
            'id' => Uuid::fromHexToBytes(Defaults::CMS_PRODUCT_DETAIL_PAGE),
            'type' => 'product_detail',
            'locked' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $connection->insert('cms_page', $page);
        $pageTranslations = new Translations(
            [
                'cms_page_id' => $page['id'],
                'name' => 'Standard Produktseite-Layout',
            ],
            [
                'cms_page_id' => $page['id'],
                'name' => 'Default product page Layout',
            ]
        );

        $this->importTranslation('cms_page_translation', $pageTranslations, $connection);

        $section = [
            'id' => Uuid::randomBytes(),
            'cms_page_id' => $page['id'],
            'position' => 0,
            'type' => 'default',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $connection->insert('cms_section', $section);

        // cms block
        $blocks = [
            [
                'id' => Uuid::randomBytes(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'cms_section_id' => $section['id'],
                'locked' => 1,
                'position' => 0,
                'type' => 'product-heading',
                'name' => 'Product heading',
                'margin_top' => '20px',
                'margin_bottom' => '20px',
                'margin_left' => '20px',
                'margin_right' => '20px',
                'background_media_mode' => 'cover',
            ],
            [
                'id' => Uuid::randomBytes(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'cms_section_id' => $section['id'],
                'locked' => 1,
                'position' => 1,
                'type' => 'gallery-buybox',
                'name' => 'Gallery buy box',
                'margin_top' => '20px',
                'margin_bottom' => '20px',
                'margin_left' => '20px',
                'margin_right' => '20px',
                'background_media_mode' => 'cover',
            ],
            [
                'id' => Uuid::randomBytes(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'cms_section_id' => $section['id'],
                'locked' => 1,
                'position' => 2,
                'type' => 'product-description-reviews',
                'name' => 'Product description and reviews',
                'margin_top' => '20px',
                'margin_bottom' => '20px',
                'margin_left' => '20px',
                'margin_right' => '20px',
                'background_media_mode' => 'cover',
            ],
            [
                'id' => Uuid::randomBytes(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'cms_section_id' => $section['id'],
                'locked' => 1,
                'position' => 3,
                'type' => 'cross-selling',
                'name' => 'Cross selling',
                'margin_top' => '20px',
                'margin_bottom' => '20px',
                'margin_left' => '20px',
                'margin_right' => '20px',
                'background_media_mode' => 'cover',
            ],
        ];

        foreach ($blocks as $block) {
            $connection->insert('cms_block', $block);
        }

        // cms slot
        $slots = [
            [
                'id' => Uuid::randomBytes(),
                'locked' => 1,
                'cms_block_id' => $blocks[0]['id'],
                'type' => 'product-name',
                'slot' => 'left',
                'version_id' => $versionId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'id' => Uuid::randomBytes(),
                'locked' => 1,
                'cms_block_id' => $blocks[0]['id'],
                'type' => 'manufacturer-logo',
                'slot' => 'right',
                'version_id' => $versionId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'id' => Uuid::randomBytes(),
                'locked' => 1,
                'cms_block_id' => $blocks[1]['id'],
                'type' => 'image-gallery',
                'slot' => 'left',
                'version_id' => $versionId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'id' => Uuid::randomBytes(),
                'locked' => 1,
                'cms_block_id' => $blocks[1]['id'],
                'type' => 'buy-box',
                'slot' => 'right',
                'version_id' => $versionId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'id' => Uuid::randomBytes(),
                'locked' => 1,
                'cms_block_id' => $blocks[2]['id'],
                'type' => 'product-description-reviews',
                'slot' => 'content',
                'version_id' => $versionId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'id' => Uuid::randomBytes(),
                'locked' => 1,
                'cms_block_id' => $blocks[3]['id'],
                'type' => 'cross-selling',
                'slot' => 'content',
                'version_id' => $versionId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        ];

        $slotTranslationData = [
            [
                'cms_slot_id' => $slots[0]['id'],
                'cms_slot_version_id' => $versionId,
                'config' => json_encode([
                    'product' => ['value' => null, 'source' => 'static'],
                    'boxLayout' => ['source' => 'static', 'value' => 'standard'],
                    'elMinWidth' => ['value' => '200px', 'source' => 'static'],
                    'displayMode' => ['source' => 'static', 'value' => 'standard'],
                ]),
            ],
            [
                'cms_slot_id' => $slots[1]['id'],
                'cms_slot_version_id' => $versionId,
                'config' => json_encode([
                    'product' => ['value' => null, 'source' => 'static'],
                    'alignment' => ['value' => null, 'source' => 'static'],
                ]),
            ],
            [
                'cms_slot_id' => $slots[2]['id'],
                'cms_slot_version_id' => $versionId,
                'config' => json_encode([
                    'zoom' => ['value' => false, 'source' => 'static'],
                    'minHeight' => ['value' => '340px', 'source' => 'static'],
                    'fullScreen' => ['value' => false, 'source' => 'static'],
                    'displayMode' => ['value' => 'standard', 'source' => 'static'],
                    'sliderItems' => ['value' => 'product.media', 'source' => 'mapped'],
                    'verticalAlign' => ['value' => null, 'source' => 'static'],
                    'navigationDots' => ['value' => null, 'source' => 'static'],
                    'galleryPosition' => ['value' => 'left', 'source' => 'static'],
                    'navigationArrows' => ['value' => 'inside', 'source' => 'static'],
                ]),
            ],
            [
                'cms_slot_id' => $slots[3]['id'],
                'cms_slot_version_id' => $versionId,
                'config' => json_encode([
                    'product' => ['value' => null, 'source' => 'static'],
                    'alignment' => ['value' => null, 'source' => 'static'],
                ]),
            ],
            [
                'cms_slot_id' => $slots[4]['id'],
                'cms_slot_version_id' => $versionId,
                'config' => json_encode([
                    'content' => ['source' => 'mapped', 'value' => 'product.name'],
                    'verticalAlign' => ['value' => null, 'source' => 'static'],
                ]),
            ],
            [
                'cms_slot_id' => $slots[5]['id'],
                'cms_slot_version_id' => $versionId,
                'config' => json_encode([
                    'url' => ['source' => 'static', 'value' => null],
                    'media' => ['source' => 'mapped', 'value' => 'product.manufacturer.media'],
                    'newTab' => ['source' => 'static', 'value' => true],
                    'minHeight' => ['source' => 'static', 'value' => null],
                    'displayMode' => ['source' => 'static', 'value' => 'cover'],
                    'verticalAlign' => ['source' => 'static', 'value' => null],
                ]),
            ],
        ];

        foreach ($slots as $slot) {
            $connection->insert('cms_slot', $slot);
        }

        foreach ($slotTranslationData as $slotTranslationDatum) {
            $slotTranslations = new Translations($slotTranslationDatum, $slotTranslationDatum);

            $this->importTranslation('cms_slot_translation', $slotTranslations, $connection);
        }
    }
}
