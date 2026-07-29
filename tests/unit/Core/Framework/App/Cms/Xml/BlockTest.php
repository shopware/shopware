<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Cms\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Cms\Xml\Block;

/**
 * @internal
 */
#[CoversClass(Block::class)]
class BlockTest extends TestCase
{
    public function testFromXml(): void
    {
        $block = self::createBlock();

        static::assertSame('teaser-block', $block->getName());
        static::assertSame('text-image', $block->getCategory());
        static::assertSame(
            [
                'en-GB' => 'Teaser block',
                'de-DE' => 'Teaser Block',
            ],
            $block->getLabel()
        );
        static::assertCount(2, $block->getSlots());
        static::assertSame('left', $block->getSlots()[0]->getName());
        static::assertSame('10px', $block->getDefaultConfig()->getMarginTop());
    }

    public function testToArray(): void
    {
        $block = self::createBlock();
        $slots = $block->getSlots();
        $defaultConfig = $block->getDefaultConfig();

        static::assertSame(
            [
                'name' => 'teaser-block',
                'category' => 'text-image',
                'label' => [
                    'en-GB' => 'Teaser block',
                    'de-DE' => 'Teaser Block',
                ],
                'slots' => $slots,
                'defaultConfig' => $defaultConfig,
            ],
            $block->toArray('en-GB')
        );
    }

    public function testToEntityArray(): void
    {
        static::assertSame(
            [
                'appId' => 'app-id',
                'name' => 'teaser-block',
                'label' => [
                    'en-GB' => 'Teaser block',
                    'de-DE' => 'Teaser Block',
                ],
                'block' => [
                    'name' => 'teaser-block',
                    'category' => 'text-image',
                    'label' => [
                        'en-GB' => 'Teaser block',
                        'de-DE' => 'Teaser Block',
                    ],
                    'slots' => [
                        'left' => [
                            'type' => 'image',
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
                            'type' => 'text',
                            'position' => 1,
                            'default' => [
                                'config' => [
                                    'content' => [
                                        'source' => 'mapped',
                                        'value' => 'product.description',
                                    ],
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
            self::createBlock()->toEntityArray('app-id', 'en-GB')
        );
    }

    private static function createBlock(): Block
    {
        return Block::fromXml(self::loadElement(<<<'XML'
<block>
    <name>teaser-block</name>
    <category>text-image</category>
    <label>Teaser block</label>
    <label lang="de-DE">Teaser Block</label>
    <slots>
        <slot name="left" type="image">
            <config>
                <config-value name="display-mode" source="static" value="cover"/>
            </config>
        </slot>
        <slot name="right" type="text">
            <config>
                <config-value name="content" source="mapped" value="product.description"/>
            </config>
        </slot>
    </slots>
    <default-config>
        <margin-bottom>5px</margin-bottom>
        <margin-top>10px</margin-top>
        <margin-left>15px</margin-left>
        <margin-right>20px</margin-right>
        <sizing-mode>boxed</sizing-mode>
        <background-color>#000</background-color>
    </default-config>
</block>
XML));
    }

    private static function loadElement(string $xml): \DOMElement
    {
        $document = new \DOMDocument();
        static::assertTrue($document->loadXML($xml));
        static::assertInstanceOf(\DOMElement::class, $document->documentElement);

        return $document->documentElement;
    }
}
