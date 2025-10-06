<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1759735553AddMissingDefaultSlotConfigurationFields;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1759735553AddMissingDefaultSlotConfigurationFields::class)]
class Migration1759735553AddMissingDefaultSlotConfigurationFieldsTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('DELETE FROM cms_page');
    }

    public function testMigrationAddsMissingFieldsForText(): void
    {
        $slotId = $this->createCmsPage('text');

        $migration = new Migration1759735553AddMissingDefaultSlotConfigurationFields();
        $migration->update($this->connection);

        $result = $this->fetchSlotConfig($slotId);

        static::assertNotEmpty($result);
        $config = json_decode($result['config'], true);
        $expectedConfig = [
            'content' => ['value' => 'test', 'source' => 'static'],
            'verticalAlign' => ['value' => null, 'source' => 'static'],
        ];

        static::assertEquals('text', $result['slot_type']);
        static::assertEquals($expectedConfig, $config);
    }

    public function testMigrationAddsMissingFieldsForImage(): void
    {
        $slotId = $this->createCmsPage('image');

        $migration = new Migration1759735553AddMissingDefaultSlotConfigurationFields();
        $migration->update($this->connection);

        $result = $this->fetchSlotConfig($slotId);

        static::assertNotEmpty($result);
        $config = json_decode($result['config'], true);
        $expectedConfig = [
            'content' => ['value' => 'test', 'source' => 'static'],
            'verticalAlign' => ['value' => 'center', 'source' => 'static'],
            'horizontalAlign' => ['value' => 'center', 'source' => 'static'],
            'isDecorative' => ['value' => false, 'source' => 'static'],
        ];

        static::assertEquals('image', $result['slot_type']);
        static::assertEquals($expectedConfig, $config);
    }

    public function testMigrationAddsMissingFieldsForProductListing(): void
    {
        $slotId = $this->createCmsPage('product-listing');

        $migration = new Migration1759735553AddMissingDefaultSlotConfigurationFields();
        $migration->update($this->connection);

        $result = $this->fetchSlotConfig($slotId);

        static::assertNotEmpty($result);
        $config = json_decode($result['config'], true);
        $expectedConfig = [
            'content' => ['value' => 'test', 'source' => 'static'],
            'boxHeadlineLevel' => ['value' => 2, 'source' => 'static'],
            'showSorting' => ['value' => true, 'source' => 'static'],
            'useCustomSorting' => ['value' => false, 'source' => 'static'],
            'availableSortings' => ['value' => [], 'source' => 'static'],
            'defaultSorting' => ['value' => '', 'source' => 'static'],
            'filters' => ['value' => 'manufacturer-filter,rating-filter,price-filter,shipping-free-filter,property-filter', 'source' => 'static'],
            'propertyWhitelist' => ['value' => [], 'source' => 'static'],
        ];

        static::assertEquals('product-listing', $result['slot_type']);
        static::assertEquals($expectedConfig, $config);
    }

    private function createCmsPage(?string $slotType): string
    {
        $id = Uuid::randomHex();
        $cmsPageRepository = static::getContainer()->get('cms_page.repository');

        $cmsPageRepository->create(
            [[
                'id' => $id,
                'type' => 'page',
                'sections' => [[
                    'type' => 'default',
                    'position' => 0,
                    'blocks' => [[
                        'type' => 'default',
                        'position' => 0,
                        'slots' => [[
                            'id' => $id,
                            'type' => $slotType,
                            'slot' => 'content',
                            'config' => ['content' => ['value' => 'test', 'source' => 'static']],
                        ]],
                    ]],
                ]],
            ]],
            Context::createDefaultContext()
        );

        return $id;
    }

    /**
     * @return array<string, mixed>|false
     */
    private function fetchSlotConfig(string $slotId): array|false
    {
        return $this->connection->fetchAssociative(
            'SELECT t.config, s.type AS slot_type
             FROM cms_slot_translation t
             INNER JOIN cms_slot s ON t.cms_slot_id = s.id
             WHERE s.id = UNHEX(:slotId)',
            ['slotId' => $slotId],
        );
    }
}
