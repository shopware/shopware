<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1774483201AddNewestProductSorting extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1774483201;
    }

    public function update(Connection $connection): void
    {
        // Check if 'newest' sorting already exists
        $exists = $connection->fetchOne(
            'SELECT COUNT(*) FROM product_sorting WHERE url_key = :key',
            ['key' => 'newest']
        );

        if ($exists) {
            return;
        }

        $this->createNewestSortingWithTranslations($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function createNewestSortingWithTranslations(Connection $connection): void
    {
        $sorting = $this->getNewestSorting();

        $translations = $sorting['translations'];

        unset($sorting['translations']);

        $connection->insert(ProductSortingDefinition::ENTITY_NAME, $sorting);

        $translations = new Translations(
            ['product_sorting_id' => $sorting['id'], 'label' => $translations['de-DE']],
            ['product_sorting_id' => $sorting['id'], 'label' => $translations['en-GB']]
        );

        $this->importTranslation('product_sorting_translation', $translations, $connection);
    }

    /**
     * @return array{id: string, url_key: string, priority: int, active: int, locked: int, fields: string, created_at: string, translations: array{de-DE: string, en-GB: string}}
     */
    private function getNewestSorting(): array
    {
        return [
            'id' => Uuid::randomBytes(),
            'url_key' => 'newest',
            'priority' => 5,
            'active' => 1,
            'locked' => 0,
            'fields' => json_encode([['field' => 'product.createdAt', 'order' => 'desc', 'priority' => 1, 'naturalSorting' => 0]], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'translations' => [
                'de-DE' => 'Neueste',
                'en-GB' => 'Newest',
            ],
        ];
    }
}
