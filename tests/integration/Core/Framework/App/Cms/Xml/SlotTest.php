<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Cms\Xml;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Cms\CmsExtensions;

/**
 * @internal
 */
class SlotTest extends TestCase
{
    public function testFromXml(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/valid/cmsExtensionsWithBlocks.xml');
        static::assertNotNull($cmsExtensions->getBlocks());

        $slots = $cmsExtensions->getBlocks()->getBlocks()[0]->getSlots();

        static::assertCount(3, $slots);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideSlots')]
    public function testSlotsFromXml(int $i, string $name, string $type, array $config): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/valid/cmsExtensionsWithBlocks.xml');
        static::assertNotNull($cmsExtensions->getBlocks());

        $slot = $cmsExtensions->getBlocks()->getBlocks()[0]->getSlots()[$i];

        static::assertSame($name, $slot->getName());
        static::assertSame($type, $slot->getType());
        static::assertEquals($config, $slot->getConfig()->toArray('en-GB'));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideSlots')]
    public function testToArray(int $i, string $name, string $type, array $config): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/valid/cmsExtensionsWithBlocks.xml');
        static::assertNotNull($cmsExtensions->getBlocks());

        $slot = $cmsExtensions->getBlocks()->getBlocks()[0]->getSlots()[$i];

        static::assertEquals(
            [
                'name' => $name,
                'type' => $type,
                'config' => $config,
            ],
            $slot->toArray('en-GB')
        );
    }

    /**
     * @return array<array<string|int|array<string, mixed>>>
     */
    public static function provideSlots(): array
    {
        return [
            [
                0,
                'name' => 'left',
                'type' => 'manufacturer-logo',
                'config' => [
                    'displayMode' => [
                        'source' => 'static',
                        'value' => 'cover',
                    ],
                ],
            ],
            [
                1,
                'name' => 'middle',
                'type' => 'product-slider',
                'config' => [
                    'backgroundColor' => [
                        'source' => 'static',
                        'value' => 'red',
                    ],
                    'displayMode' => [
                        'source' => 'static',
                        'value' => 'auto',
                    ],
                ],
            ],
            [
                2,
                'name' => 'right',
                'type' => 'buy-box',
                'config' => [
                    'displayMode' => [
                        'source' => 'static',
                        'value' => 'contain',
                    ],
                ],
            ],
        ];
    }
}
