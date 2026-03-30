<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Components;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\Twig\Components\TwigComponent;

/**
 * @internal
 */
#[CoversClass(TwigComponent::class)]
class TwigComponentTest extends TestCase
{
    public function testConstruction(): void
    {
        $component = new TwigComponent(
            'Button:Primary',
            '/path/to/Button/Primary.html.twig',
            'Storefront'
        );

        static::assertSame('Button:Primary', $component->name);
        static::assertSame('/path/to/Button/Primary.html.twig', $component->path);
        static::assertSame('Storefront', $component->namespace);
    }

    public function testGetBaseName(): void
    {
        $component = new TwigComponent(
            'Button:Primary',
            '/path/to/Button/Primary.html.twig',
            'Storefront'
        );

        static::assertSame('Primary', $component->getBaseName());
    }

    public function testGetBaseNameSingleLevel(): void
    {
        $component = new TwigComponent(
            'Button',
            '/path/to/Button.html.twig',
            'Storefront'
        );

        static::assertSame('Button', $component->getBaseName());
    }

    public function testGetBaseNameMultipleLevels(): void
    {
        $component = new TwigComponent(
            'Sw:Components:Button:Primary',
            '/path/to/Sw/Components/Button/Primary.html.twig',
            'CustomNamespace'
        );

        static::assertSame('Primary', $component->getBaseName());
    }

    /**
     * @return \Generator<string, array{string, string, string, bool, string}>
     */
    public static function getTagDataProvider(): \Generator
    {
        yield 'storefront namespace simple' => [
            'Button',
            'Storefront',
            '/path/to/Button.html.twig',
            false,
            'Button',
        ];

        yield 'storefront namespace nested' => [
            'Button:Primary',
            'Storefront',
            '/path/to/Button/Primary.html.twig',
            false,
            'Button:Primary',
        ];

        yield 'custom namespace simple' => [
            'Button',
            'CustomPlugin',
            '/path/to/Button.html.twig',
            false,
            'CustomPlugin:Button',
        ];

        yield 'custom namespace nested' => [
            'Button:Primary',
            'CustomPlugin',
            '/path/to/Button/Primary.html.twig',
            false,
            'CustomPlugin:Button:Primary',
        ];

        yield 'storefront index component' => [
            'Button:index',
            'Storefront',
            '/path/to/Button/index.html.twig',
            true,
            'Button',
        ];

        yield 'custom namespace index component' => [
            'Button:index',
            'CustomPlugin',
            '/path/to/Button/index.html.twig',
            true,
            'CustomPlugin:Button',
        ];

        yield 'nested index component' => [
            'Components:Button:index',
            'Storefront',
            '/path/to/Components/Button/index.html.twig',
            true,
            'Components:Button',
        ];
    }

    #[DataProvider('getTagDataProvider')]
    public function testGetTag(string $name, string $namespace, string $path, bool $isIndex, string $expectedTag): void
    {
        $component = new TwigComponent($name, $path, $namespace);

        static::assertSame($isIndex, $component->isIndexComponent());
        static::assertSame($expectedTag, $component->getTag());
    }

    /**
     * @return \Generator<string, array{string, string, string}>
     */
    public static function relativeNamespacePathDataProvider(): \Generator
    {
        yield 'storefront simple' => [
            'Button',
            'Storefront',
            'Button',
        ];

        yield 'storefront nested' => [
            'Button:Primary',
            'Storefront',
            'Button/Primary',
        ];

        yield 'custom namespace simple' => [
            'Button',
            'CustomPlugin',
            'CustomPlugin/Button',
        ];

        yield 'custom namespace nested' => [
            'Components:Button:Primary',
            'CustomPlugin',
            'CustomPlugin/Components/Button/Primary',
        ];
    }

    #[DataProvider('relativeNamespacePathDataProvider')]
    public function testGetRelativeNamespacePath(string $name, string $namespace, string $expected): void
    {
        $component = new TwigComponent($name, '/path/to/component.html.twig', $namespace);

        static::assertSame($expected, $component->getRelativeNamespacePath());
    }

    /**
     * @return \Generator<string, array{string, string, string}>
     */
    public static function relativeNamespaceDirectoryDataProvider(): \Generator
    {
        yield 'storefront simple' => [
            'Button',
            'Storefront',
            '',
        ];

        yield 'storefront nested' => [
            'Button:Primary',
            'Storefront',
            'Button',
        ];

        yield 'custom namespace simple' => [
            'Button',
            'CustomPlugin',
            'CustomPlugin',
        ];

        yield 'custom namespace nested' => [
            'Components:Button:Primary',
            'CustomPlugin',
            'CustomPlugin/Components/Button',
        ];
    }

    #[DataProvider('relativeNamespaceDirectoryDataProvider')]
    public function testGetRelativeNamespaceDirectory(string $name, string $namespace, string $expected): void
    {
        $component = new TwigComponent($name, '/path/to/component.html.twig', $namespace);

        static::assertSame($expected, $component->getRelativeNamespaceDirectory());
    }

    public function testIsIndexComponentWithIndexFile(): void
    {
        $component = new TwigComponent(
            'Button:index',
            '/path/to/Button/index.html.twig',
            'Storefront'
        );

        static::assertTrue($component->isIndexComponent());
    }

    public function testIsIndexComponentWithNonIndexFile(): void
    {
        $component = new TwigComponent(
            'Button:Primary',
            '/path/to/Button/Primary.html.twig',
            'Storefront'
        );

        static::assertFalse($component->isIndexComponent());
    }

    public function testIsIndexComponentCaseInsensitive(): void
    {
        $component = new TwigComponent(
            'Button:index',
            '/path/to/Button/INDEX.HTML.TWIG',
            'Storefront'
        );

        static::assertTrue($component->isIndexComponent());
    }

    public function testGetDirectory(): void
    {
        $component = new TwigComponent(
            'Button',
            '/path/to/components/Button.html.twig',
            'Storefront'
        );

        static::assertSame('/path/to/components', $component->getDirectory());
    }

    public function testGetStylePath(): void
    {
        $component = new TwigComponent('Button', '/path/to/components/Button.html.twig', 'Storefront');

        static::assertSame('/path/to/components/Button.scss', $component->getStylePath());
    }

    public function testGetScriptPath(): void
    {
        $component = new TwigComponent('Button', '/path/to/components/Button.html.twig', 'Storefront');

        static::assertSame('/path/to/components/Button.js', $component->getScriptPath());
    }

    public function testPublicProperties(): void
    {
        $component = new TwigComponent('Button', '/path/to/Button.html.twig', 'Storefront');

        $component->name = 'NewButton';
        static::assertSame('NewButton', $component->name);

        $component->path = '/new/path/Button.html.twig';
        static::assertSame('/new/path/Button.html.twig', $component->path);

        $component->namespace = 'CustomPlugin';
        static::assertSame('CustomPlugin', $component->namespace);
    }

    public function testNestedComponentWithMultipleColons(): void
    {
        $component = new TwigComponent(
            'Sw:Forms:Input:Text',
            '/path/to/Sw/Forms/Input/Text.html.twig',
            'CustomBundle'
        );

        static::assertSame('Text', $component->getBaseName());
        static::assertSame('CustomBundle:Sw:Forms:Input:Text', $component->getTag());
        static::assertSame('CustomBundle/Sw/Forms/Input/Text', $component->getRelativeNamespacePath());
        static::assertSame('CustomBundle/Sw/Forms/Input', $component->getRelativeNamespaceDirectory());
    }
}
