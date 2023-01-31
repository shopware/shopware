<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1586334003AddParentIdToProductProfile extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1586334003;
    }

    public function update(Connection $connection): void
    {
        $productProfileId = $connection->executeQuery(
            'SELECT `id` FROM `import_export_profile` WHERE `name` = :name AND `system_default` = 1 AND source_entity = "product"',
            ['name' => 'Default product']
        )->fetchOne();

        if ($productProfileId) {
            $mapping = $this->getProductMapping();
            $connection->update('import_export_profile', ['mapping' => json_encode($mapping, \JSON_THROW_ON_ERROR)], ['id' => $productProfileId]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @return list<array{key: string, mappedKey: string}>
     */
    private function getProductMapping(): array
    {
        return [
            ['key' => 'id', 'mappedKey' => 'id'],
            ['key' => 'parentId', 'mappedKey' => 'parent_id'],

            ['key' => 'productNumber', 'mappedKey' => 'product_number'],
            ['key' => 'active', 'mappedKey' => 'active'],
            ['key' => 'stock', 'mappedKey' => 'stock'],
            ['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name'],
            ['key' => 'translations.DEFAULT.description', 'mappedKey' => 'description'],

            ['key' => 'price.DEFAULT.net', 'mappedKey' => 'price_net'],
            ['key' => 'price.DEFAULT.gross', 'mappedKey' => 'price_gross'],

            ['key' => 'tax.id', 'mappedKey' => 'tax_id'],
            ['key' => 'tax.taxRate', 'mappedKey' => 'tax_rate'],
            ['key' => 'tax.name', 'mappedKey' => 'tax_name'],

            ['key' => 'cover.media.id', 'mappedKey' => 'cover_media_id'],
            ['key' => 'cover.media.url', 'mappedKey' => 'cover_media_url'],
            ['key' => 'cover.media.translations.DEFAULT.title', 'mappedKey' => 'cover_media_title'],
            ['key' => 'cover.media.translations.DEFAULT.alt', 'mappedKey' => 'cover_media_alt'],

            ['key' => 'manufacturer.id', 'mappedKey' => 'manufacturer_id'],
            ['key' => 'manufacturer.translations.DEFAULT.name', 'mappedKey' => 'manufacturer_name'],

            ['key' => 'categories', 'mappedKey' => 'categories'],
            ['key' => 'visibilities.all', 'mappedKey' => 'sales_channel'],
        ];
    }
}
