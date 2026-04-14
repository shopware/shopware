<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Components;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\Twig\Components\TwigComponent;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentCollection;

/**
 * @internal
 */
#[CoversClass(TwigComponentCollection::class)]
class TwigComponentCollectionTest extends TestCase
{
    public function testConstructionWithEmptyArray(): void
    {
        $collection = new TwigComponentCollection();

        static::assertCount(0, $collection);
    }

    public function testConstructionWithComponents(): void
    {
        $component1 = new TwigComponent('Button', '/path/to/Button.html.twig', 'Storefront');
        $component2 = new TwigComponent('Card', '/path/to/Card.html.twig', 'Storefront');

        $collection = new TwigComponentCollection([$component1, $component2]);

        static::assertCount(2, $collection);
        static::assertSame($component1, $collection->get('Button'));
        static::assertSame($component2, $collection->get('Card'));
    }

    public function testConstructionWithComponentsUsesTagAsKey(): void
    {
        $component1 = new TwigComponent('Button:Primary', '/path/to/Button/Primary.html.twig', 'Storefront');
        $component2 = new TwigComponent('Button', '/path/to/Button.html.twig', 'CustomPlugin');

        $collection = new TwigComponentCollection([$component1, $component2]);

        static::assertCount(2, $collection);
        static::assertSame($component1, $collection->get('Button:Primary'));
        static::assertSame($component2, $collection->get('CustomPlugin:Button'));
    }

    public function testAddComponent(): void
    {
        $collection = new TwigComponentCollection();
        $component = new TwigComponent('Button', '/path/to/Button.html.twig', 'Storefront');

        $collection->add($component);

        static::assertCount(1, $collection);
        static::assertSame($component, $collection->get('Button'));
    }

    public function testAddComponentUsesTagAsKey(): void
    {
        $collection = new TwigComponentCollection();
        $component = new TwigComponent('Button', '/path/to/Button.html.twig', 'CustomPlugin');

        $collection->add($component);

        static::assertSame($component, $collection->get('CustomPlugin:Button'));
    }

    public function testAddMultipleComponents(): void
    {
        $collection = new TwigComponentCollection();

        $component1 = new TwigComponent('Button', '/path/to/Button.html.twig', 'Storefront');
        $component2 = new TwigComponent('Card', '/path/to/Card.html.twig', 'Storefront');
        $component3 = new TwigComponent('Modal', '/path/to/Modal.html.twig', 'CustomPlugin');

        $collection->add($component1);
        $collection->add($component2);
        $collection->add($component3);

        static::assertCount(3, $collection);
        static::assertSame($component1, $collection->get('Button'));
        static::assertSame($component2, $collection->get('Card'));
        static::assertSame($component3, $collection->get('CustomPlugin:Modal'));
    }

    public function testAddReplacesExistingComponentWithSameTag(): void
    {
        $collection = new TwigComponentCollection();

        $component1 = new TwigComponent('Button', '/path/to/Button.html.twig', 'Storefront');
        $component2 = new TwigComponent('Button', '/path/to/NewButton.html.twig', 'Storefront');

        $collection->add($component1);
        $collection->add($component2);

        static::assertCount(1, $collection);
        static::assertSame($component2, $collection->get('Button'));
        static::assertSame('/path/to/NewButton.html.twig', $collection->get('Button')->path);
    }

    public function testAddThrowsTypeErrorForInvalidType(): void
    {
        $this->expectException(\TypeError::class);

        /** @phpstan-ignore-next-line argument.type - Intentionally passing wrong type to test validation */
        new TwigComponentCollection([new \stdClass()]);
    }

    public function testIterateOverCollection(): void
    {
        $component1 = new TwigComponent('Button', '/path/to/Button.html.twig', 'Storefront');
        $component2 = new TwigComponent('Card', '/path/to/Card.html.twig', 'Storefront');

        $collection = new TwigComponentCollection([$component1, $component2]);

        $components = [];
        foreach ($collection as $key => $component) {
            $components[$key] = $component;
        }

        static::assertCount(2, $components);
        static::assertArrayHasKey('Button', $components);
        static::assertArrayHasKey('Card', $components);
        static::assertSame($component1, $components['Button']);
        static::assertSame($component2, $components['Card']);
    }

    public function testHasMethod(): void
    {
        $component = new TwigComponent('Button', '/path/to/Button.html.twig', 'Storefront');
        $collection = new TwigComponentCollection([$component]);

        static::assertTrue($collection->has('Button'));
        static::assertFalse($collection->has('Card'));
    }

    public function testRemoveMethod(): void
    {
        $component1 = new TwigComponent('Button', '/path/to/Button.html.twig', 'Storefront');
        $component2 = new TwigComponent('Card', '/path/to/Card.html.twig', 'Storefront');

        $collection = new TwigComponentCollection([$component1, $component2]);

        static::assertCount(2, $collection);

        $collection->remove('Button');

        static::assertCount(1, $collection);
        static::assertFalse($collection->has('Button'));
        static::assertTrue($collection->has('Card'));
    }

    public function testIndexComponentHandling(): void
    {
        $indexComponent = new TwigComponent('Button:index', '/path/to/Button/index.html.twig', 'Storefront');

        $collection = new TwigComponentCollection([$indexComponent]);

        // Index components should use the shortened tag as key
        static::assertTrue($collection->has('Button'));
        static::assertSame($indexComponent, $collection->get('Button'));
    }

    public function testMixedNamespaceComponents(): void
    {
        $storefrontComponent = new TwigComponent('Button', '/path/to/Button.html.twig', 'Storefront');
        $pluginComponent = new TwigComponent('Button', '/path/to/Button.html.twig', 'MyPlugin');
        $anotherPluginComponent = new TwigComponent('Button:Primary', '/path/to/Button/Primary.html.twig', 'AnotherPlugin');

        $collection = new TwigComponentCollection([
            $storefrontComponent,
            $pluginComponent,
            $anotherPluginComponent,
        ]);

        static::assertCount(3, $collection);
        static::assertTrue($collection->has('Button'));
        static::assertTrue($collection->has('MyPlugin:Button'));
        static::assertTrue($collection->has('AnotherPlugin:Button:Primary'));

        static::assertSame($storefrontComponent, $collection->get('Button'));
        static::assertSame($pluginComponent, $collection->get('MyPlugin:Button'));
        static::assertSame($anotherPluginComponent, $collection->get('AnotherPlugin:Button:Primary'));
    }
}
