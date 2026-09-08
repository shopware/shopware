<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\PrimitiveDefaultProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AbstractLayoutMutation::class)]
class AbstractLayoutMutationTest extends TestCase
{
    /**
     * The three default producers share one shape rule and keep no copy of it, so each is asserted against
     * {@see PropertyType::storedDefault()} rather than against a literal: a producer that diverged from the rule
     * would have to diverge from the rule's own output to pass.
     */
    #[DataProvider('seedingPropertyProvider')]
    #[TestDox('every default producer emits the stored shape the property type defines: $_dataName')]
    public function testDefaultProducersAgreeWithTheStoredShapeRule(string $key, PropertyType $propertyType): void
    {
        $produced = $this->produceDefaults($key, $propertyType);

        static::assertSame([$key => $propertyType->storedDefault()], $produced['provider']);
        static::assertSame([$key => $propertyType->storedDefault()], $produced['seeder']);
        static::assertSame([$key => $propertyType->storedDefault()], $produced['mutation']);
    }

    /**
     * @return iterable<string, array{string, PropertyType}>
     */
    public static function seedingPropertyProvider(): iterable
    {
        yield 'translatable string with a declared default' => [
            'text',
            new PropertyType('string', true, null, 'Willkommen'),
        ];

        yield 'non-translatable string with a declared default' => [
            'mode',
            new PropertyType('string', false, null, 'auto-fit'),
        ];

        yield 'non-translatable integer with a declared default' => [
            'maxImageWidth',
            new PropertyType('integer', false, null, 1360),
        ];

        // A false default is not an absent default, so a producer testing truthiness instead of identity fails.
        yield 'non-translatable boolean with a false default' => [
            'reverse',
            new PropertyType('boolean', false, null, false),
        ];
    }

    #[DataProvider('nonSeedingPropertyProvider')]
    #[TestDox('every default producer emits no key for a property that seeds nothing: $_dataName')]
    public function testDefaultProducersEmitNoKeyForAPropertyThatSeedsNothing(string $key, PropertyType $propertyType): void
    {
        $produced = $this->produceDefaults($key, $propertyType);

        static::assertSame([], $produced['provider']);
        static::assertSame([], $produced['seeder']);
        static::assertSame([], $produced['mutation']);
    }

    /**
     * @return iterable<string, array{string, PropertyType}>
     */
    public static function nonSeedingPropertyProvider(): iterable
    {
        yield 'translatable string without a declared default' => [
            'ariaLabel',
            new PropertyType('string', true, null, null),
        ];

        yield 'non-translatable string without a declared default' => [
            'alt',
            new PropertyType('string', false, null, null),
        ];

        // A reference property carries a default the primitive gate must drop, so a producer that stopped
        // consulting isPrimitive() fails here rather than only on a type declaring no reference property.
        yield 'reference property carrying a default' => [
            'product',
            new PropertyType(SalesChannelProductEntity::class, false, null, 'ignored-default'),
        ];
    }

    /**
     * The output of all three default producers for a one-property type, each unwrapped to raw PHP values so the
     * three are directly comparable.
     *
     * @return array{provider: array<string, mixed>, seeder: array<string, mixed>, mutation: array<string, mixed>}
     */
    private function produceDefaults(string $key, PropertyType $propertyType): array
    {
        $registry = $this->registry('Sw:Block', $key, $propertyType);

        $seeded = (new LayoutDefaultSeeder($registry, new PrimitiveDefaultProvider()))
            ->seed([StoredElementBuilder::create('Sw:Block', 'el')->build()]);

        $mutation = new class extends AbstractLayoutMutation {
            public function apply(StoredTree $tree): StoredTree
            {
                return $tree;
            }

            /**
             * @return array<string, StoredValue>
             */
            public function exposeDefaults(AbstractContentSystemElementTypeRegistry $registry, string $type): array
            {
                return $this->primitiveDefaults($registry, $type);
            }
        };

        return [
            'provider' => (new PrimitiveDefaultProvider())->forType($registry, 'Sw:Block'),
            'seeder' => $this->unwrap($seeded[0]->properties()),
            'mutation' => $this->unwrap($mutation->exposeDefaults($registry, 'Sw:Block')),
        ];
    }

    private function registry(string $type, string $key, PropertyType $propertyType): AbstractContentSystemElementTypeRegistry
    {
        $specification = new ContentSystemElementTypeSpecification(
            $type,
            $type,
            '',
            null,
            null,
            new CopilotSpecification('', []),
            [$key => new PropertySpecification('prop', $propertyType, false, '', '', null)],
            [],
        );

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => $name === $type);
        $registry->method('get')->willReturn($specification);

        return $registry;
    }

    /**
     * @param array<string, StoredValue> $values
     *
     * @return array<string, mixed>
     */
    private function unwrap(array $values): array
    {
        return array_map(static fn (StoredValue $value): mixed => $value->jsonSerialize(), $values);
    }
}
