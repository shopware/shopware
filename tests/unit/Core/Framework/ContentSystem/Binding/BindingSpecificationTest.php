<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingInput;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;

/**
 * @internal
 */
#[CoversClass(BindingSpecification::class)]
class BindingSpecificationTest extends TestCase
{
    #[TestDox('toSchema() renames a resolves entry source to the wire key "loader"')]
    public function testToSchemaRenamesResolvesSourceToLoaderKey(): void
    {
        $resolves = ['image' => new LoaderBinding('product', ['filter' => 'active'])];

        $specification = new BindingSpecification('binding-1', 'cms_text', 'Text', $resolves, [], 'core');

        $schema = $specification->toSchema();

        static::assertArrayHasKey('image', $schema['resolves']);
        static::assertArrayHasKey('loader', $schema['resolves']['image']);
        static::assertArrayNotHasKey('source', $schema['resolves']['image']);
        static::assertSame('product', $schema['resolves']['image']['loader']);
        static::assertSame(['filter' => 'active'], $schema['resolves']['image']['config']);
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('serializesInputProvider')]
    #[TestDox('toSchema() serializes an input: $_dataName')]
    public function testToSchemaSerializesInput(BindingInput $input, array $expected): void
    {
        $specification = new BindingSpecification('binding-1', 'cms_text', 'Text', [], ['title' => $input], 'core');

        $schema = $specification->toSchema();

        static::assertSame($expected, $schema['inputs']['title']);
    }

    #[TestDox('toSchema() includes id, type and label, and emits [] (not {}) for empty resolves and inputs')]
    public function testToSchemaEmitsEmptyArraysForEmptyResolvesAndInputs(): void
    {
        $specification = new BindingSpecification('binding-1', 'cms_text', 'Text', [], [], 'core');

        $schema = $specification->toSchema();

        static::assertSame('binding-1', $schema['id']);
        static::assertSame('cms_text', $schema['type']);
        static::assertSame('Text', $schema['label']);
        static::assertSame([], $schema['resolves']);
        static::assertSame([], $schema['inputs']);
    }

    #[TestDox('isDefault() is false when the id differs from the type')]
    public function testIsDefaultFalseWhenIdDiffersFromType(): void
    {
        $specification = new BindingSpecification('binding-1', 'cms_text', 'Text', [], [], 'core');

        static::assertFalse($specification->isDefault());
    }

    #[TestDox('isDefault() is true when the id equals the type')]
    public function testIsDefaultTrueWhenIdEqualsType(): void
    {
        $specification = new BindingSpecification('cms_text', 'cms_text', 'Text', [], [], 'core');

        static::assertTrue($specification->isDefault());
    }

    #[TestDox('toSchema() emits default:false when the id differs from the type')]
    public function testToSchemaEmitsDefaultFalseWhenIdDiffersFromType(): void
    {
        $specification = new BindingSpecification('binding-1', 'cms_text', 'Text', [], [], 'core');

        static::assertFalse($specification->toSchema()['default']);
    }

    #[TestDox('toSchema() emits default:true when the id equals the type')]
    public function testToSchemaEmitsDefaultTrueWhenIdEqualsType(): void
    {
        $specification = new BindingSpecification('cms_text', 'cms_text', 'Text', [], [], 'core');

        static::assertTrue($specification->toSchema()['default']);
    }

    /**
     * @return iterable<string, array{BindingInput, array<string, mixed>}>
     */
    public static function serializesInputProvider(): iterable
    {
        yield 'without default' => [new BindingInput(false, null, false), ['required' => false]];
        yield 'with default value' => [new BindingInput(true, 'Untitled', false), ['default' => 'Untitled', 'required' => false]];
        yield 'with explicit null default' => [new BindingInput(true, null, false), ['default' => null, 'required' => false]];
        yield 'required without default' => [new BindingInput(false, null, true), ['required' => true]];
        yield 'required with default value' => [new BindingInput(true, 'Untitled', true), ['default' => 'Untitled', 'required' => true]];
    }
}
