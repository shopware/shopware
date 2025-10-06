<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1759735553AddMissingDefaultSlotConfigurationFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1759735553;
    }

    public function update(Connection $connection): void
    {
        $this->addMissingDefaultSlotConfigurationFields($connection);
    }

    private function addMissingDefaultSlotConfigurationFields(Connection $connection): void
    {
        $defaultLayouts = $connection->fetchAllAssociative(
            'SELECT t.cms_slot_id, t.config, s.type AS slot_type
             FROM cms_slot_translation t
             INNER JOIN cms_slot s ON t.cms_slot_id = s.id
             WHERE t.config IS NOT NULL
               AND s.type IN ("image", "product-listing", "text");'
        );

        foreach ($defaultLayouts as $layout) {
            $slotConfig = json_decode($layout['config'], true);

            if ($slotConfig === null) {
                continue;
            }

            $updated = false;

            foreach ($slotConfig as $slotId => $config) {
                if ($layout['slot_type'] === 'text' && !isset($config['verticalAlign'])) {
                    $slotConfig['verticalAlign'] = [
                        'source' => 'static',
                        'value' => null,
                    ];
                    $updated = true;
                }

                if ($layout['slot_type'] === 'image') {
                    if (!isset($config['verticalAlign'])) {
                        $slotConfig['verticalAlign'] = [
                            'source' => 'static',
                            'value' => 'center',
                        ];
                        $updated = true;
                    }

                    if (!isset($config['horizontalAlign'])) {
                        $slotConfig['horizontalAlign'] = [
                            'source' => 'static',
                            'value' => 'center',
                        ];
                        $updated = true;
                    }

                    if (!isset($config['isDecorative'])) {
                        $slotConfig['isDecorative'] = [
                            'source' => 'static',
                            'value' => false,
                        ];
                        $updated = true;
                    }
                }

                if ($layout['slot_type'] === 'product-listing') {
                    if (!isset($config['boxHeadlineLevel'])) {
                        $slotConfig['boxHeadlineLevel'] = [
                            'source' => 'static',
                            'value' => 2,
                        ];
                        $updated = true;
                    }

                    if (!isset($config['showSorting'])) {
                        $slotConfig['showSorting'] = [
                            'source' => 'static',
                            'value' => true,
                        ];
                        $updated = true;
                    }

                    if (!isset($config['useCustomSorting'])) {
                        $slotConfig['useCustomSorting'] = [
                            'source' => 'static',
                            'value' => false,
                        ];
                        $updated = true;
                    }

                    if (!isset($config['availableSortings'])) {
                        $slotConfig['availableSortings'] = [
                            'source' => 'static',
                            'value' => [],
                        ];
                        $updated = true;
                    }

                    if (!isset($config['defaultSorting'])) {
                        $slotConfig['defaultSorting'] = [
                            'source' => 'static',
                            'value' => '',
                        ];
                        $updated = true;
                    }

                    if (!isset($config['filters'])) {
                        $slotConfig['filters'] = [
                            'source' => 'static',
                            'value' => 'manufacturer-filter,rating-filter,price-filter,shipping-free-filter,property-filter',
                        ];
                        $updated = true;
                    }

                    if (!isset($config['propertyWhitelist'])) {
                        $slotConfig['propertyWhitelist'] = [
                            'source' => 'static',
                            'value' => [],
                        ];
                        $updated = true;
                    }
                }
            }

            if ($updated) {
                $connection->executeStatement(
                    'UPDATE cms_slot_translation SET config = :config WHERE cms_slot_id = :id;',
                    [
                        'config' => json_encode($slotConfig),
                        'id' => $layout['cms_slot_id'],
                    ]
                );
            }
        }
    }
}
