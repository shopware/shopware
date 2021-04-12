<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Cms\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Cms\CmsExtensions;

class BlockTest extends TestCase
{
    public function testFromXml(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/valid/cmsExtensionsWithBlocks.xml');

        static::assertCount(2, $cmsExtensions->getBlocks()->getBlocks());

        $firstBlock = $cmsExtensions->getBlocks()->getBlocks()[0];
        static::assertEquals('first-block-name', $firstBlock->getName());
        static::assertEquals('text-image', $firstBlock->getCategory());
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
     * @dataProvider provideEntityArrays
     */
    public function testToEntityArray(int $i, array $expectedEntityArray): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/valid/cmsExtensionsWithBlocks.xml');

        static::assertEquals(
            $expectedEntityArray,
            $cmsExtensions->getBlocks()->getBlocks()[$i]->toEntityArray('app-id', 'en-GB')
        );
    }

    public function provideEntityArrays(): array
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
                                'config' => [
                                    'displayMode' => [
                                        'source' => 'static',
                                        'value' => 'cover',
                                    ],
                                ],
                            ],
                            'middle' => [
                                'type' => 'product-slider',
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
                            'right' => [
                                'type' => 'buy-box',
                                'config' => [
                                    'displayMode' => [
                                        'source' => 'static',
                                        'value' => 'contain',
                                    ],
                                ],
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
                                'config' => [
                                    'displayMode' => [
                                        'source' => 'static',
                                        'value' => 'cover',
                                    ],
                                ],
                            ],
                            'right' => [
                                'type' => 'image',
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
