<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Cms\Xml;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Cms\CmsExtensions;

/**
 * @internal
 */
class BlockTest extends TestCase
{
    public function testFromXml(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/valid/cmsExtensionsWithBlocks.xml');
        static::assertNotNull($cmsExtensions->getBlocks());

        static::assertCount(2, $cmsExtensions->getBlocks()->getBlocks());

        $firstBlock = $cmsExtensions->getBlocks()->getBlocks()[0];
        static::assertSame('first-block-name', $firstBlock->getName());
        static::assertSame('text-image', $firstBlock->getCategory());
        static::assertCount(3, $firstBlock->getSlots());
        static::assertCount(6, $firstBlock->getDefaultConfig()->toArray('en-GB'));
        static::assertEquals(
            [
                'en-GB' => 'First block from app',
                'de-DE' => 'Erster Block einer App',
            ],
            $firstBlock->getLabel()
        );
    }

    public function testToArray(): void
    {
        $manifest = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/valid/cmsExtensionsWithBlocks.xml');
        static::assertNotNull($manifest->getBlocks());
        static::assertCount(2, $manifest->getBlocks()->getBlocks());

        $firstBlock = $manifest->getBlocks()->getBlocks()[0];
        $slots = $firstBlock->getSlots();
        $defaultConfig = $firstBlock->getDefaultConfig();

        static::assertEquals(
            [
                'name' => 'first-block-name',
                'category' => 'text-image',
                'label' => [
                    'en-GB' => 'First block from app',
                    'de-DE' => 'Erster Block einer App',
                ],
                'slots' => $slots,
                'defaultConfig' => $defaultConfig,
            ],
            $firstBlock->toArray('en-GB')
        );

        $secondBlock = $manifest->getBlocks()->getBlocks()[1];
        $slots = $secondBlock->getSlots();
        $defaultConfig = $secondBlock->getDefaultConfig();

        static::assertEquals(
            [
                'name' => 'second-block-name',
                'category' => 'text',
                'label' => [
                    'en-GB' => 'Second block from app',
                    'de-DE' => 'Zweiter Block einer App',
                ],
                'slots' => $slots,
                'defaultConfig' => $defaultConfig,
            ],
            $secondBlock->toArray('en-GB')
        );
    }

    /**
     * @param array<string, mixed> $expectedEntityArray
     */
    #[DataProvider('provideEntityArrays')]
    public function testToEntityArray(int $i, array $expectedEntityArray): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/valid/cmsExtensionsWithBlocks.xml');

        static::assertNotNull($cmsExtensions->getBlocks());
        static::assertEquals(
            $expectedEntityArray,
            $cmsExtensions->getBlocks()->getBlocks()[$i]->toEntityArray('app-id', 'en-GB')
        );
    }

    /**
     * @return array<array<int|array<string, mixed>>>
     */
    public static function provideEntityArrays(): array
    {
        return [
            [
                0,
                [
                    'appId' => 'app-id',
                    'name' => 'first-block-name',
                    'label' => [
                        'en-GB' => 'First block from app',
                        'de-DE' => 'Erster Block einer App',
                    ],
                    'block' => [
                        'name' => 'first-block-name',
                        'category' => 'text-image',
                        'label' => [
                            'en-GB' => 'First block from app',
                            'de-DE' => 'Erster Block einer App',
                        ],
                        'slots' => [
                            'left' => [
                                'type' => 'manufacturer-logo',
                                'default' => [
                                    'config' => [
                                        'displayMode' => [
                                            'source' => 'static',
                                            'value' => 'cover',
                                        ],
                                    ],
                                ],
                                'position' => 0,
                            ],
                            'middle' => [
                                'type' => 'product-slider',
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
                                'position' => 1,
                            ],
                            'right' => [
                                'type' => 'buy-box',
                                'default' => [
                                    'config' => [
                                        'displayMode' => [
                                            'source' => 'static',
                                            'value' => 'contain',
                                        ],
                                    ],
                                ],
                                'position' => 2,
                            ],
                        ],
                        'defaultConfig' => [
                            'marginTop' => '10px',
                            'marginRight' => '20px',
                            'marginBottom' => '5px',
                            'marginLeft' => '15px',
                            'sizingMode' => 'boxed',
                            'backgroundColor' => '#000',
                        ],
                    ],
                ],
            ],
            [
                1,
                [
                    'appId' => 'app-id',
                    'name' => 'second-block-name',
                    'label' => [
                        'en-GB' => 'Second block from app',
                        'de-DE' => 'Zweiter Block einer App',
                    ],
                    'block' => [
                        'name' => 'second-block-name',
                        'category' => 'text',
                        'label' => [
                            'en-GB' => 'Second block from app',
                            'de-DE' => 'Zweiter Block einer App',
                        ],
                        'slots' => [
                            'left' => [
                                'type' => 'form',
                                'default' => [
                                    'config' => [
                                        'displayMode' => [
                                            'source' => 'static',
                                            'value' => 'cover',
                                        ],
                                    ],
                                ],
                                'position' => 0,
                            ],
                            'right' => [
                                'type' => 'image',
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
                                'position' => 1,
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
                ],
            ],
        ];
    }
}
