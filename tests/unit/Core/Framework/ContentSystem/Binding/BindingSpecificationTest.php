<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
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
    public function testAccessorsReturnConstructorValues(): void
    {
        $resolves = ['image' => new LoaderBinding('product', [])];
        $inputs = ['title' => new BindingInput(false, null)];

        $specification = new BindingSpecification('binding-1', 'cms_text', 'Text', $resolves, $inputs, 'app-plugin');

        static::assertSame('binding-1', $specification->id());
        static::assertSame('cms_text', $specification->type());
        static::assertSame('Text', $specification->label());
        static::assertSame($resolves, $specification->resolves());
        static::assertSame($inputs, $specification->inputs());
        static::assertSame('app-plugin', $specification->source());
    }

    public function testSourceDefaultsToEmptyStringWhenOmitted(): void
    {
        $specification = new BindingSpecification('binding-1', 'cms_text', 'Text', [], []);

        static::assertSame('', $specification->source());
    }

    #[TestDox('toSchema() renames a resolves entry source to the wire key "loader"')]
    public function testToSchemaRenamesResolvesSourceToLoaderKey(): void
    {
        $resolves = ['image' => new LoaderBinding('product', ['filter' => 'active'])];

        $specification = new BindingSpecification('binding-1', 'cms_text', 'Text', $resolves, []);

        $schema = $specification->toSchema();

        static::assertArrayHasKey('image', $schema['resolves']);
        static::assertArrayHasKey('loader', $schema['resolves']['image']);
        static::assertArrayNotHasKey('source', $schema['resolves']['image']);
        static::assertSame('product', $schema['resolves']['image']['loader']);
        static::assertSame(['filter' => 'active'], $schema['resolves']['image']['config']);
    }

    #[TestDox('toSchema() serializes an input with a default as ["default" => value]')]
    public function testToSchemaSerializesInputWithDefault(): void
    {
        $inputs = ['title' => new BindingInput(true, 'Untitled')];

        $specification = new BindingSpecification('binding-1', 'cms_text', 'Text', [], $inputs);

        $schema = $specification->toSchema();

        static::assertSame(['default' => 'Untitled'], $schema['inputs']['title']);
    }

    #[TestDox('toSchema() serializes an input without a default as an empty array')]
    public function testToSchemaSerializesInputWithoutDefaultAsEmptyArray(): void
    {
        $inputs = ['title' => new BindingInput(false, null)];

        $specification = new BindingSpecification('binding-1', 'cms_text', 'Text', [], $inputs);

        $schema = $specification->toSchema();

        static::assertSame([], $schema['inputs']['title']);
        static::assertArrayNotHasKey('default', $schema['inputs']['title']);
    }

    #[TestDox('toSchema() distinguishes an explicit null default from no default')]
    public function testToSchemaSerializesExplicitNullDefaultDistinctlyFromNoDefault(): void
    {
        $inputs = ['title' => new BindingInput(true, null)];

        $specification = new BindingSpecification('binding-1', 'cms_text', 'Text', [], $inputs);

        $schema = $specification->toSchema();

        static::assertSame(['default' => null], $schema['inputs']['title']);
    }

    #[TestDox('toSchema() emits [] (not {}) for empty resolves and inputs maps')]
    public function testToSchemaEmitsEmptyArraysForEmptyResolvesAndInputs(): void
    {
        $specification = new BindingSpecification('binding-1', 'cms_text', 'Text', [], []);

        $schema = $specification->toSchema();

        static::assertSame([], $schema['resolves']);
        static::assertSame([], $schema['inputs']);
    }

    public function testToSchemaIncludesIdTypeAndLabel(): void
    {
        $specification = new BindingSpecification('binding-1', 'cms_text', 'Text', [], []);

        $schema = $specification->toSchema();

        static::assertSame('binding-1', $schema['id']);
        static::assertSame('cms_text', $schema['type']);
        static::assertSame('Text', $schema['label']);
    }
}
