<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

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
use Shopware\Core\Migration\V6_7\Migration1740321707SetAutoplayTimeoutAndSpeedSettingsForProductSlider;

/**
 * @internal
 *
 * @phpstan-type cmsConfigProperty array{value: mixed, source: string}
 */
#[Package('framework')]
#[CoversClass(Migration1740321707SetAutoplayTimeoutAndSpeedSettingsForProductSlider::class)]
class Migration1740321707SetAutoplayTimeoutAndSpeedSettingsForProductSliderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1740321707SetAutoplayTimeoutAndSpeedSettingsForProductSlider();
        static::assertSame(1740321707, $migration->getCreationTimestamp());
    }

    #[DataProvider('productSliderConfigDataProvider')]
    public function testMigrationUpdatesProductSliderConfigWithAutoplayTimeoutAndSpeed(?int $autoplayTimeout, ?int $speed): void
    {
        $affectedSlotId = $this->prepareOldDatabaseEntry($autoplayTimeout, $speed);

        $migration = new Migration1740321707SetAutoplayTimeoutAndSpeedSettingsForProductSlider();
        $migration->update($this->connection);

        $expectedConfig = $this->getExpectedProductSliderConfig($autoplayTimeout, $speed);
        $actualConfig = $this->getActualConfig($affectedSlotId);

        ksort($expectedConfig);
        ksort($actualConfig);

        static::assertSame($expectedConfig, $actualConfig);
    }

    /**
     * @return array<string, list<int|null>>
     */
    public static function productSliderConfigDataProvider(): array
    {
        return [
            'config with autoplay timeout and speed' => [10000, 500],
            'config with autoplay timeout' => [10000, null],
            'config with speed' => [null, 500],
            'config without autoplay timeout and speed' => [null, null],
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

    private function prepareOldDatabaseEntry(?int $autoplayTimeout, ?int $speed): string
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
                            'config' => $this->getActualSampleProductSliderConfig($autoplayTimeout, $speed),
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
    private function getActualSampleProductSliderConfig(?int $autoplayTimeout, ?int $speed): array
    {
        return [
            ...($autoplayTimeout !== null ? ['autoplayTimeout' => [
                'value' => $autoplayTimeout,
                'source' => 'static',
            ]] : []),
            ...($speed !== null ? ['speed' => [
                'value' => $speed,
                'source' => 'static',
            ]] : []),
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
            'navigationArrows' => [
                'value' => 'outside',
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
    private function getExpectedProductSliderConfig(?int $autoplayTimeout, ?int $speed): array
    {
        return [
            'autoplayTimeout' => [
                'value' => $autoplayTimeout ?? 5000,
                'source' => 'static',
            ],
            'speed' => [
                'value' => $speed ?? 300,
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
            'navigationArrows' => [
                'value' => 'outside',
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
