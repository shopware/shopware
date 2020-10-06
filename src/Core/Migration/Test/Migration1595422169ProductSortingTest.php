<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingDefinition;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Migration1595422169AddProductSorting;
use Shopware\Core\Migration\Migration1600338271AddTopsellerSorting;

class Migration1595422169ProductSortingTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    /**
     * @after
     */
    public function restoreOldDatabase(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $connection->executeUpdate('DELETE FROM `product_sorting_translation`');
        $connection->executeUpdate('DELETE FROM `product_sorting`');

        (new Migration1595422169AddProductSorting())->createDefaultSortingsWithTranslations($connection);
        (new Migration1600338271AddTopsellerSorting())->createDefaultSortingsWithTranslations($connection);
    }

    /**
     * @before
     */
    public function restoreNewDatabase(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $connection->executeUpdate('DROP TABLE IF EXISTS `product_sorting_translation`');
        $connection->executeUpdate('DROP TABLE IF EXISTS `product_sorting`');

        $migration = new Migration1595422169AddProductSorting();
        $migration->update($connection);

        $migration = new Migration1600338271AddTopsellerSorting();
        $migration->update($connection);
    }

    public function testMigration(): void
    {
        $this->connection->executeUpdate('DELETE FROM `product_sorting_translation`');
        $this->connection->executeUpdate('DELETE FROM `product_sorting`');

        $defaultSorting = $this->fetchSystemConfig();
        $sortings = $this->migrationCases();

        $this->insert($sortings);

        $actual = $this->fetchSortings();

        foreach ($actual as $index => $sorting) {
            $actual[$index]['fields'] = \json_decode($sorting['fields'], true);
        }

        foreach ($sortings as $index => $sorting) {
            $sortings[$index]['fields'] = \json_decode($sorting['fields'], true);
        }

        static::assertEquals($sortings, $actual);
        static::assertJsonStringEqualsJsonString('{"_value": "name-asc"}', $defaultSorting);
    }

    public function testMigrationWithFranceAsDefault(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $connection->executeUpdate('DROP TABLE IF EXISTS `product_sorting_translation`');
        $connection->executeUpdate('DROP TABLE IF EXISTS `product_sorting`');

        $this->getContainer()->get(Connection::class)
            ->executeUpdate(
                'UPDATE `language` SET locale_id = :locale WHERE id = :id',
                ['locale' => $this->getLocaleId('fr-FR'), 'id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
            );

        $migration = new Migration1595422169AddProductSorting();
        $migration->update($connection);

        $this->getContainer()->get(Connection::class)
            ->executeUpdate(
                'UPDATE `language` SET locale_id = :locale WHERE id = :id',
                ['locale' => $this->getLocaleId('en-GB'), 'id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
            );

        $translations = $this->connection->fetchAll(
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
    }

    private function migrationCases(): array
    {
        return [
            [
                'url_key' => 'single-field-test',
                'fields' => \json_encode([
                    [
                        'field' => 'product.name',
                        'order' => 'asc',
                        'priority' => 0,
                        'naturalSorting' => 0,
                    ],
                ]),
                'priority' => '1',
                'label' => 'A-Z',
            ],
            [
                'url_key' => 'multiple-field-test',
                'fields' => \json_encode([
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
                ]),
                'priority' => '0',
                'label' => 'Custom Sort',
            ],
        ];
    }

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

    private function fetchSortings(): array
    {
        return $this->connection->fetchAll('
            SELECT `product_sorting`.url_key,
                   `product_sorting`.fields,
                   `product_sorting`.priority,
                   `product_sorting_translation`.label
            FROM `product_sorting`
                LEFT JOIN `product_sorting_translation`
                ON `product_sorting`.id = `product_sorting_translation`.product_sorting_id
            ORDER BY `product_sorting`.priority DESC;
        ');
    }

    private function fetchSystemConfig(): string
    {
        return $this->connection->fetchColumn('
            SELECT configuration_value
            FROM `system_config`
            WHERE configuration_key = "core.listing.defaultSorting";
        ');
    }

    private function getLocaleId(string $code): string
    {
        return $this->getContainer()->get(Connection::class)
            ->fetchColumn('SELECT id FROM locale WHERE code = :code', ['code' => $code]);
    }
}
