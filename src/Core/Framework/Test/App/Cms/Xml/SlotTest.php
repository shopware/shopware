<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Cms\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Cms\CmsExtensions;

class SlotTest extends TestCase
{
    public function testFromXml(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/valid/cmsExtensionsWithBlocks.xml');
        $slots = $cmsExtensions->getBlocks()->getBlocks()[0]->getSlots();

        static::assertCount(3, $slots);
    }

    /**
     * @dataProvider provideSlots
     */
    public function testSlotsFromXml(int $i, string $name, string $type, array $config): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/valid/cmsExtensionsWithBlocks.xml');
        $slot = $cmsExtensions->getBlocks()->getBlocks()[0]->getSlots()[$i];

        static::assertEquals($name, $slot->getName());
        static::assertEquals($type, $slot->getType());
        static::assertEquals($config, $slot->getConfig()->toArray('en-GB'));
    }

    /**
     * @dataProvider provideSlots
     */
    public function testToArray(int $i, string $name, string $type, array $config): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/valid/cmsExtensionsWithBlocks.xml');
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

    public function provideSlots(): array
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
