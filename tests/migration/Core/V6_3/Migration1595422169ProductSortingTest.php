<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingDefinition;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_3\Migration1595422169AddProductSorting;
use Shopware\Core\Migration\V6_3\Migration1600338271AddTopsellerSorting;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1595422169AddProductSorting
 *
 * @phpstan-type Sorting array{url_key: string, fields:string, priority: string, label: string}
 */
class Migration1595422169ProductSortingTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    /**
     * @after
     */
    public function restoreOldDatabase(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $connection->executeStatement('DELETE FROM `product_sorting_translation`');
        $connection->executeStatement('DELETE FROM `product_sorting`');

        (new Migration1595422169AddProductSorting())->createDefaultSortingsWithTranslations($connection);
        (new Migration1600338271AddTopsellerSorting())->createDefaultSortingsWithTranslations($connection);
    }

    /**
     * @before
     */
    public function restoreNewDatabase(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $connection->rollBack();

        $connection->executeStatement('DROP TABLE IF EXISTS `product_sorting_translation`');
        $connection->executeStatement('DROP TABLE IF EXISTS `product_sorting`');

        $migration = new Migration1595422169AddProductSorting();
        $migration->update($connection);

        $migration = new Migration1600338271AddTopsellerSorting();
        $migration->update($connection);

        $connection->beginTransaction();
    }

    public function testMigration(): void
    {
        $this->connection->executeStatement('DELETE FROM `product_sorting_translation`');
        $this->connection->executeStatement('DELETE FROM `product_sorting`');

        $defaultSorting = $this->fetchSystemConfig();
        $sortings = $this->migrationCases();

        $this->insert($sortings);

        $actual = $this->fetchSortings();

        foreach ($actual as $index => $sorting) {
            $actual[$index]['fields'] = json_decode((string) $sorting['fields'], true, 512, \JSON_THROW_ON_ERROR);
        }

        foreach ($sortings as $index => $sorting) {
            $sortings[$index]['fields'] = json_decode((string) $sorting['fields'], true, 512, \JSON_THROW_ON_ERROR);
        }

        static::assertEquals($sortings, $actual);
        static::assertJsonStringEqualsJsonString('{"_value": "name-asc"}', $defaultSorting);
    }

    public function testMigrationWithFranceAsDefault(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $connection->rollBack();

        $connection->executeStatement('DROP TABLE IF EXISTS `product_sorting_translation`');
        $connection->executeStatement('DROP TABLE IF EXISTS `product_sorting`');

        KernelLifecycleManager::getConnection()
            ->executeStatement(
                'UPDATE `language` SET locale_id = :locale WHERE id = :id',
                ['locale' => $this->getLocaleId('fr-FR'), 'id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
            );

        $migration = new Migration1595422169AddProductSorting();
        $migration->update($connection);

        KernelLifecycleManager::getConnection()
            ->executeStatement(
                'UPDATE `language` SET locale_id = :locale WHERE id = :id',
                ['locale' => $this->getLocaleId('en-GB'), 'id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
            );

        $translations = $this->connection->fetchAllAssociative(
            'SELECT label FROM product_sorting_translation WHERE language_id = :id',
            ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        $translations = array_column($translations, 'label');
        sort($translations);

        $expected = [
            'Name A-Z',
            'Name Z-A',
            'Price ascending',
            'Price descending',
            'Top results',
        ];

        sort($expected);

        static::assertEquals($expected, $translations);

        $connection->beginTransaction();
    }

    /**
     * @return Sorting[]
     */
    private function migrationCases(): array
    {
        return [
            [
                'url_key' => 'single-field-test',
                'fields' => json_encode([
                    [
                        'field' => 'product.name',
                        'order' => 'asc',
                        'priority' => 0,
                        'naturalSorting' => 0,
                    ],
                ], \JSON_THROW_ON_ERROR),
                'priority' => '1',
                'label' => 'A-Z',
            ],
            [
                'url_key' => 'multiple-field-test',
                'fields' => json_encode([
                    [
                        'field' => 'product.name',
                        'order' => 'asc',
                        'priority' => 1,
                        'naturalSorting' => 1,
                    ],
                    [
                        'field' => 'product.listingPrices',
                        'order' => 'desc',
                        'priority' => 0,
                        'naturalSorting' => 0,
                    ],
                ], \JSON_THROW_ON_ERROR),
                'priority' => '0',
                'label' => 'Custom Sort',
            ],
        ];
    }

    /**
     * @param Sorting[] $sortings
     */
    private function insert(array $sortings): void
    {
        foreach ($sortings as $sorting) {
            $id = Uuid::randomBytes();

            $this->connection->insert(ProductSortingDefinition::ENTITY_NAME, [
                'id' => $id,
                'active' => 1,
                'locked' => 0,
                'priority' => $sorting['priority'],
                'url_key' => $sorting['url_key'],
                'fields' => $sorting['fields'],
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
            ]);

            $this->connection->insert(ProductSortingTranslationDefinition::ENTITY_NAME, [
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'product_sorting_id' => $id,
                'label' => $sorting['label'],
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
            ]);
        }
    }

    /**
     * @return Sorting[]
     */
    private function fetchSortings(): array
    {
        /** @var Sorting[] $result */
        $result = $this->connection->fetchAllAssociative('
            SELECT `product_sorting`.url_key,
                   `product_sorting`.fields,
                   `product_sorting`.priority,
                   `product_sorting_translation`.label
            FROM `product_sorting`
                LEFT JOIN `product_sorting_translation`
                ON `product_sorting`.id = `product_sorting_translation`.product_sorting_id
            ORDER BY `product_sorting`.priority DESC;
        ');

        return $result;
    }

    private function fetchSystemConfig(): string
    {
        return $this->connection->fetchOne('
            SELECT configuration_value
            FROM `system_config`
            WHERE configuration_key = "core.listing.defaultSorting";
        ');
    }

    private function getLocaleId(string $code): string
    {
        return $this->connection
            ->fetchOne('SELECT id FROM locale WHERE code = :code', ['code' => $code]);
    }
}
