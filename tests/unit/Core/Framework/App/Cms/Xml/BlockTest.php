<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Cms\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Cms\CmsExtensions as CmsManifest;
use Shopware\Core\Framework\App\Cms\Xml\Block;
use Shopware\Core\Framework\App\Cms\Xml\DefaultConfig;
use Shopware\Core\Framework\App\Cms\Xml\Slot;

/**
 * @internal
 *
 * @phpstan-type BlockArray array{
 *     name: string,
 *     category: string,
 *     label: array<string, string>,
 *     slots: array<string, array{
 *         type: string,
 *         position: int,
 *         default: array{
 *             config: array<string, array{
 *                 source: string,
 *                 value: string
 *             }>
 *         }
 *     }>,
 *     defaultConfig: array<string, string>
 * }
 * @phpstan-type BlockEntityArray array{
 *     appId: string,
 *     name: string,
 *     label: array<string, string>,
 *     block: BlockArray
 * }
 */
#[CoversClass(Block::class)]
class BlockTest extends TestCase
{
    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    public function testFromXml(): void
    {
        $manifest = CmsManifest::createFromXmlFile(__DIR__ . '/../../_fixtures/Resources/cms.xml');
        $actualOuterBlocks = $manifest->getBlocks();
        static::assertNotNull($actualOuterBlocks);

        $actualBlocks = $actualOuterBlocks->getBlocks();
        $expectedBlocks = $this->getExpectedEntityArrayBlocks();

        foreach ($expectedBlocks as $index => $expectedBlock) {
            $expectedBlockInner = $expectedBlock['block'];

            static::assertSame($expectedBlockInner['name'], $actualBlocks[$index]->getName());
            static::assertSame($expectedBlockInner['category'], $actualBlocks[$index]->getCategory());
            static::assertSame($expectedBlockInner['label'], $actualBlocks[$index]->getLabel());
            static::assertSame($expectedBlockInner['defaultConfig'], $actualBlocks[$index]->getDefaultConfig()->toArray('en-GB'));

            $slotNames = array_keys($expectedBlockInner['slots']);
            foreach (array_values($expectedBlockInner['slots']) as $slotIndex => $expectedSlot) {
                $actualSlot = $actualBlocks[$index]->getSlots()[$slotIndex];

                static::assertSame($slotNames[$slotIndex], $actualSlot->getName());
                static::assertSame($expectedSlot['type'], $actualSlot->getType());
                static::assertSame($expectedSlot['default']['config'], $actualSlot->getConfig()->toArray('en-GB'));
            }
        }
    }

    public function testToArray(): void
    {
        $manifest = CmsManifest::createFromXmlFile(__DIR__ . '/../../_fixtures/Resources/cms.xml');
        $actualOuterBlocks = $manifest->getBlocks();
        static::assertNotNull($actualOuterBlocks);

        $actualBlocks = $actualOuterBlocks->getBlocks();
        $actualBlockArray = array_map(fn ($block) => $block->toArray('en-GB'), $actualBlocks);

        $expectedBlocks = $this->getExpectedEntityArrayBlocks();

        foreach ($expectedBlocks as $index => $expectedBlock) {
            $expectedBlockInner = $expectedBlock['block'];

            static::assertSame($expectedBlockInner['name'], $actualBlockArray[$index]['name']);
            static::assertSame($expectedBlockInner['category'], $actualBlockArray[$index]['category']);
            static::assertSame($expectedBlockInner['label'], $actualBlockArray[$index]['label']);

            $defaultConfig = $actualBlockArray[$index]['defaultConfig'];
            static::assertInstanceOf(DefaultConfig::class, $defaultConfig);

            static::assertSame($expectedBlockInner['defaultConfig'], $defaultConfig->toArray('en-GB'));

            $slotNames = array_keys($expectedBlockInner['slots']);
            foreach (array_values($expectedBlockInner['slots']) as $slotIndex => $expectedSlot) {
                $actualSlot = $actualBlockArray[$index]['slots'][$slotIndex];
                static::assertInstanceOf(Slot::class, $actualSlot);

                static::assertSame($slotNames[$slotIndex], $actualSlot->getName());
                static::assertSame($expectedSlot['type'], $actualSlot->getType());
                static::assertSame($expectedSlot['default']['config'], $actualSlot->getConfig()->toArray('en-GB'));
            }
        }
    }

    public function testToEntityArray(): void
    {
        $manifest = CmsManifest::createFromXmlFile(__DIR__ . '/../../_fixtures/Resources/cms.xml');

        $actualOuterBlocks = $manifest->getBlocks();
        static::assertNotNull($actualOuterBlocks);

        $actualBlocks = $actualOuterBlocks->getBlocks();

        $appId = 'niceBlockApp';
        $defaultLocale = 'en-GB';

        $actualBlocks = [
            $actualBlocks[0]->toEntityArray($appId, $defaultLocale),
            $actualBlocks[1]->toEntityArray($appId, $defaultLocale),
        ];
        $expectedBlocks = $this->getExpectedEntityArrayBlocks();

        static::assertCount(2, $actualBlocks);
        static::assertSame($expectedBlocks, $actualBlocks);
    }

    /**
     * @return array<BlockEntityArray>
     */
    private function getExpectedEntityArrayBlocks(): array
    {
        return [[
            'appId' => 'niceBlockApp',
            'name' => 'my-first-block',
            'label' => [
                'en-GB' => 'First block from app',
                'de-DE' => 'Erster Block einer App',
            ],
            'block' => [
                'name' => 'my-first-block',
                'category' => 'text-image',
                'label' => [
                    'en-GB' => 'First block from app',
                    'de-DE' => 'Erster Block einer App',
                ],
                'slots' => [
                    'left' => [
                        'type' => 'manufacturer-logo',
                        'position' => 0,
                        'default' => [
                            'config' => [
                                'displayMode' => [
                                    'source' => 'static',
                                    'value' => 'cover',
                                ],
                            ],
                        ],
                    ],
                    'middle' => [
                        'type' => 'image-gallery',
                        'position' => 1,
                        'default' => [
                            'config' => [
                                'displayMode' => [
                                    'source' => 'static',
                                    'value' => 'auto',
                                ],
                                'minHeight' => [
                                    'source' => 'static',
                                    'value' => '300px',
                                ],
                            ],
                        ],
                    ],
                    'right' => [
                        'type' => 'buy-box',
                        'position' => 2,
                        'default' => [
                            'config' => [
                                'displayMode' => [
                                    'source' => 'static',
                                    'value' => 'contain',
                                ],
                            ],
                        ],
                    ],
                ],
                'defaultConfig' => [
                    'marginTop' => '20px',
                    'marginRight' => '20px',
                    'marginBottom' => '20px',
                    'marginLeft' => '20px',
                    'sizingMode' => 'boxed',
                    'backgroundColor' => '#000',
                ],
            ],
        ], [
            'appId' => 'niceBlockApp',
            'name' => 'my-second-block',
            'label' => [
                'en-GB' => 'Second block from app',
                'de-DE' => 'Zweiter Block einer App',
            ],
            'block' => [
                'name' => 'my-second-block',
                'category' => 'text-image',
                'label' => [
                    'en-GB' => 'Second block from app',
                    'de-DE' => 'Zweiter Block einer App',
                ],
                'slots' => [
                    'left' => [
                        'type' => 'form',
                        'position' => 0,
                        'default' => [
                            'config' => [
                                'displayMode' => [
                                    'source' => 'static',
                                    'value' => 'cover',
                                ],
                            ],
                        ],
                    ],
                    'right' => [
                        'type' => 'youtube-video',
                        'position' => 1,
                        'default' => [
                            'config' => [
                                'displayMode' => [
                                    'source' => 'static',
                                    'value' => 'contain',
                                ],
                            ],
                        ],
                    ],
                    'middle' => [
                        'type' => 'image',
                        'position' => 2,
                        'default' => [
                            'config' => [
                                'displayMode' => [
                                    'source' => 'static',
                                    'value' => 'auto',
                                ],
                                'backgroundColor' => [
                                    'source' => 'static',
                                    'value' => 'red',
                                ],
                            ],
                        ],
                    ],
                ],
                'defaultConfig' => [
                    'marginTop' => '20px',
                    'marginRight' => '20px',
                    'marginBottom' => '20px',
                    'marginLeft' => '20px',
                    'sizingMode' => 'boxed',
                    'backgroundColor' => '#f00',
                ],
            ],
        ]];
    }
}
