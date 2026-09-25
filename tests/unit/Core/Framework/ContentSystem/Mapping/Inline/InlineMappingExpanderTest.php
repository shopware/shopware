<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping\Inline;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingExpander;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingInterpolator;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\ContentSystemPropertyProjectionRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubPathStruct;

/**
 * The stored-tree rewrite that turns tokens into text before the mint reads a property.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(InlineMappingExpander::class)]
class InlineMappingExpanderTest extends TestCase
{
    private const COMPONENT = 'Sw:Content:Text';

    public function testExpandsATokenInAnInlineMappableProperty(): void
    {
        $element = $this->element(['text' => StoredValue::ofString('Buy the {{map:product.name}} today')]);

        $expanded = $this->expander()->expand([$element], $this->ambient(), 'product');

        static::assertSame('Buy the Shirt today', $expanded[0]->property('text')?->asString());
    }

    /**
     * The flag is the gate, not the token's own presence. Expanding on the strength of a token alone would make
     * `inlineMappable` advisory.
     */
    #[TestDox('leaves a token alone in a property that did not declare inlineMappable')]
    public function testLeavesATokenInAPropertyThatDidNotOptIn(): void
    {
        $element = $this->element(['other' => StoredValue::ofString('{{map:product.name}}')]);

        $expanded = $this->expander()->expand([$element], $this->ambient(), 'product');

        static::assertSame('{{map:product.name}}', $expanded[0]->property('other')?->asString());
    }

    #[TestDox('leaves an uncatalogued token verbatim')]
    public function testLeavesAnUncataloguedTokenVerbatim(): void
    {
        $element = $this->element(['text' => StoredValue::ofString('{{map:product.secret}}')]);

        $expanded = $this->expander()->expand([$element], $this->ambient(), 'product');

        static::assertSame('{{map:product.secret}}', $expanded[0]->property('text')?->asString());
    }

    #[TestDox('does nothing at all when the layout has no root source')]
    public function testReturnsTheForestUntouchedWithoutARootSource(): void
    {
        $element = $this->element(['text' => StoredValue::ofString('{{map:product.name}}')]);
        $forest = [$element];

        static::assertSame($forest, $this->expander()->expand($forest, $this->ambient(), null));
    }

    /**
     * Same rule as `Layout/Scaffolding/StoredTreePreparer`: a token is a property of the authored value at a declared
     * key, and no property specification exists to have flagged a string buried in a container.
     */
    #[TestDox('does not reach into a list or map property, even one holding a token')]
    public function testDoesNotExpandInsideContainerProperties(): void
    {
        $element = $this->element([
            'text' => StoredValue::ofList([StoredValue::ofString('{{map:product.name}}')]),
        ]);

        $expanded = $this->expander()->expand([$element], $this->ambient(), 'product');

        static::assertSame('{{map:product.name}}', $expanded[0]->property('text')?->asList()[0]->asString());
    }

    #[TestDox('expands tokens in slot children too')]
    public function testExpandsNestedElements(): void
    {
        $child = $this->element(['text' => StoredValue::ofString('{{map:product.name}}')], id: 'child');
        $parent = new StoredElement(
            id: 'parent',
            component: self::COMPONENT,
            slots: ['content' => [$child]],
        );

        $expanded = $this->expander()->expand([$parent], $this->ambient(), 'product');

        static::assertSame('Shirt', $expanded[0]->slots['content'][0]->property('text')?->asString());
    }

    #[TestDox('leaves a non-string property untouched')]
    public function testLeavesNonStringPropertiesUntouched(): void
    {
        $element = $this->element(['text' => StoredValue::ofInt(42)]);

        $expanded = $this->expander()->expand([$element], $this->ambient(), 'product');

        static::assertSame(42, $expanded[0]->property('text')?->asInt());
    }

    /**
     * An unregistered component is reported as an intrinsic violation elsewhere; here it simply cannot be shown to
     * have opted in, so nothing is expanded.
     */
    #[TestDox('leaves a token alone on an unregistered component')]
    public function testLeavesATokenAloneOnAnUnregisteredComponent(): void
    {
        $element = $this->element(['text' => StoredValue::ofString('{{map:product.name}}')], component: 'Sw:Unknown');

        $expanded = $this->expander()->expand([$element], $this->ambient(), 'product');

        static::assertSame('{{map:product.name}}', $expanded[0]->property('text')?->asString());
    }

    /**
     * @return array<string, mixed>
     */
    private function ambient(): array
    {
        return ['product' => new StubPathStruct(name: 'Shirt')];
    }

    /**
     * @param array<string, StoredValue> $properties
     */
    private function element(array $properties, string $id = 'el-1', string $component = self::COMPONENT): StoredElement
    {
        return new StoredElement(id: $id, component: $component, properties: $properties);
    }

    private function expander(): InlineMappingExpander
    {
        $candidates = static::createStub(AbstractContentSystemMappingCandidateRegistry::class);
        $candidates->method('forRootSource')->willReturn([
            'product.name' => new MappingCandidate(
                path: 'product.name',
                label: 'a label',
                description: 'a description',
                group: 'basic',
                valueType: 'string',
            ),
        ]);

        return new InlineMappingExpander(
            $this->typeRegistry(),
            new InlineMappingTokenParser(),
            new InlineMappingInterpolator(
                $candidates,
                new ContentSystemPropertyProjectionRegistry([]),
                new ContextPathResolver(),
            ),
        );
    }

    /**
     * `text` opts in, `other` does not, which is what makes the flag check observable rather than incidental.
     */
    private function typeRegistry(): AbstractContentSystemElementTypeRegistry
    {
        $spec = ContentSystemElementTypeSpecificationBuilder::create(self::COMPONENT)
            ->primitive('text', 'string', inlineMappable: true)
            ->primitive('other', 'string')
            ->build();

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => $name === self::COMPONENT);
        $registry->method('get')->willReturnCallback(
            static fn (): ContentSystemElementTypeSpecification => $spec
        );

        return $registry;
    }
}
