<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1732608755MigrateNavigationSettingsForProductSlider;

/**
 * @internal
 *
 * @phpstan-type cmsConfigProperty array{value: mixed, source: string}
 */
#[Package('core')]
#[CoversClass(Migration1732608755MigrateNavigationSettingsForProductSlider::class)]
class Migration1732608755MigrateNavigationSettingsForProductSliderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1732608755MigrateNavigationSettingsForProductSlider();
        static::assertSame(1732608755, $migration->getCreationTimestamp());
    }

    #[DataProvider('productSliderConfigDataProvider')]
    public function testMigrationUpdatesProductSliderConfigWithNavigation(bool $hasNavigation): void
    {
        $affectedSlotId = $this->prepareOldDatabaseEntry($hasNavigation);

        $migration = new Migration1732608755MigrateNavigationSettingsForProductSlider();
        $migration->update($this->connection);

        $expectedConfig = $this->getExpectedProductSliderConfig($hasNavigation);
        $actualConfig = $this->getActualConfig($affectedSlotId);

        ksort($expectedConfig);
        ksort($actualConfig);

        static::assertSame($expectedConfig, $actualConfig);
    }

    /**
     * @return array<string, list<bool>>
     */
    public static function productSliderConfigDataProvider(): array
    {
        return [
            'config had navigation' => [true],
            'config had no navigation' => [false],
        ];
    }

    /**
     * @return array<string, cmsConfigProperty>
     */
    public function getActualConfig(string $affectedSlotId): array
    {
        $cmsSlotRepository = static::getContainer()->get('cms_slot.repository');
        $criteria = new Criteria();
        $criteria->setIds([$affectedSlotId]);

        /** @var CmsSlotCollection $cmsSlots */
        $cmsSlots = $cmsSlotRepository->search($criteria, Context::createDefaultContext())->getEntities();

        /** @var CmsSlotEntity $cmsSlot */
        $cmsSlot = $cmsSlots->first();

        /** @var array<string, cmsConfigProperty> $cmsSlotConfig */
        $cmsSlotConfig = $cmsSlot->getConfig();

        return $cmsSlotConfig;
    }

    private function prepareOldDatabaseEntry(bool $hasNavigation): string
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
                        'type' => 'product-slider',
                        'position' => 0,
                        'slots' => [[
                            'id' => $id,
                            'type' => 'product-slider',
                            'slot' => 'content',
                            'config' => $this->getActualSampleProductSliderConfig($hasNavigation),
                        ]],
                    ]],
                ]],
            ]],
            Context::createDefaultContext(),
        );

        return $id;
    }

    /**
     * @return array<string, cmsConfigProperty>
     */
    private function getActualSampleProductSliderConfig(bool $hasNavigation): array
    {
        return [
            'navigation' => [
                'value' => $hasNavigation,
                'source' => 'static',
            ],
            'title' => [
                'value' => 'demo',
                'source' => 'static',
            ],
            'border' => [
                'value' => false,
                'source' => 'static',
            ],
            'rotate' => [
                'value' => false,
                'source' => 'static',
            ],
            'products' => [
                'value' => [],
                'source' => 'static',
            ],
            'boxLayout' => [
                'value' => 'standard',
                'source' => 'static',
            ],
            'elMinWidth' => [
                'value' => '300px',
                'source' => 'static',
            ],
            'displayMode' => [
                'value' => 'standard',
                'source' => 'static',
            ],
            'verticalAlign' => [
                'value' => null,
                'source' => 'static',
            ],
            'productStreamLimit' => [
                'value' => 10,
                'source' => 'static',
            ],
            'productStreamSorting' => [
                'value' => 'name:ASC',
                'source' => 'static',
            ],
        ];
    }

    /**
     * @return array<string, cmsConfigProperty>
     */
    private function getExpectedProductSliderConfig(bool $hasNavigation): array
    {
        return [
            'navigationArrows' => [
                'value' => $hasNavigation ? 'outside' : 'none',
                'source' => 'static',
            ],
            'title' => [
                'value' => 'demo',
                'source' => 'static',
            ],
            'border' => [
                'value' => false,
                'source' => 'static',
            ],
            'rotate' => [
                'value' => false,
                'source' => 'static',
            ],
            'products' => [
                'value' => [],
                'source' => 'static',
            ],
            'boxLayout' => [
                'value' => 'standard',
                'source' => 'static',
            ],
            'elMinWidth' => [
                'value' => '300px',
                'source' => 'static',
            ],
            'displayMode' => [
                'value' => 'standard',
                'source' => 'static',
            ],
            'verticalAlign' => [
                'value' => null,
                'source' => 'static',
            ],
            'productStreamLimit' => [
                'value' => 10,
                'source' => 'static',
            ],
            'productStreamSorting' => [
                'value' => 'name:ASC',
                'source' => 'static',
            ],
        ];
    }
}
