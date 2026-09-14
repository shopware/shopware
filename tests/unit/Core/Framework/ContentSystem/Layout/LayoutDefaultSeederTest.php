<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\StoredDefaultProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutDefaultSeeder::class)]
class LayoutDefaultSeederTest extends TestCase
{
    #[TestDox('seeds a missing primitive default and ignores reference properties on a stored element')]
    public function testSeedsPrimitiveDefaultIgnoringReferences(): void
    {
        $seeded = $this->seeder()->seed([StoredElementBuilder::create('Sw:Block', 'el')->build()]);

        static::assertSame(['headline' => 'Default headline'], $this->rawProperties($seeded[0]));
    }

    #[TestDox('does not overwrite an authored primitive value on a stored element')]
    public function testKeepsAuthoredValue(): void
    {
        $element = StoredElementBuilder::create('Sw:Block', 'el')->withProperty('headline', 'Authored')->build();

        $seeded = $this->seeder()->seed([$element]);

        static::assertSame(['headline' => 'Authored'], $this->rawProperties($seeded[0]));
    }

    #[TestDox('keeps an authored null rather than replacing it with the type default')]
    public function testKeepsAuthoredNull(): void
    {
        $element = StoredElementBuilder::create('Sw:Block', 'el')->withProperty('headline', null)->build();

        $seeded = $this->seeder()->seed([$element]);

        static::assertSame(['headline' => null], $this->rawProperties($seeded[0]));
    }

    #[TestDox('seeds missing nested property defaults on a stored element')]
    public function testSeedsNestedDefaults(): void
    {
        $seeded = $this->seeder()->seed([StoredElementBuilder::create('Sw:Grid:Container', 'el')->build()]);

        static::assertSame(
            ['padding' => ['xs' => '0 20px 0 20px', 'sm' => '0 20px 0 20px', 'md' => '0 20px 0 20px']],
            $this->rawProperties($seeded[0]),
        );
    }

    #[TestDox('does not merge missing nested defaults into an authored top-level property')]
    public function testKeepsAuthoredNestedPropertyWithoutMergingDefaults(): void
    {
        $element = StoredElementBuilder::create('Sw:Grid:Container', 'el')->withProperty('padding', ['md' => '10px'])->build();

        $seeded = $this->seeder()->seed([$element]);

        static::assertSame(['padding' => ['md' => '10px']], $this->rawProperties($seeded[0]));
    }

    #[TestDox('seeds primitive defaults on slot descendants')]
    public function testSeedsSlotDescendants(): void
    {
        $root = StoredElementBuilder::create('Sw:Block', 'root')
            ->withSlot('content', [StoredElementBuilder::create('Sw:Block', 'child')->build()])
            ->build();

        $seeded = $this->seeder()->seed([$root]);

        static::assertSame(['headline' => 'Default headline'], $this->rawProperties($seeded[0]->slots['content'][0]));
    }

    #[TestDox('leaves a node whose component type is not registered untouched')]
    public function testNoOpsOnUnregisteredComponent(): void
    {
        $seeded = $this->seeder()->seed([StoredElementBuilder::create('Sw:Unregistered', 'el')->build()]);

        static::assertSame([], $this->rawProperties($seeded[0]));
    }

    /**
     * @return array<string, mixed>
     */
    private function rawProperties(mixed $element): array
    {
        static::assertInstanceOf(StoredElement::class, $element);

        return array_map(static fn (StoredValue $value): mixed => $value->jsonSerialize(), $element->properties());
    }

    private function seeder(): LayoutDefaultSeeder
    {
        // 'product' carries a non-null default on a non-primitive type so the exclusion is isolated to
        // isPrimitive() rather than being ambiguous with the "default is null" guard StoredDefaultProvider
        // also checks.
        $nestedProperties = [
            'xs' => new PropertySpecification('xs', new PropertyType('string', false, null, '0 20px 0 20px'), false, '', '', null),
            'sm' => new PropertySpecification('sm', new PropertyType('string', false, null, '0 20px 0 20px'), false, '', '', null),
            'md' => new PropertySpecification('md', new PropertyType('string', false, null, '0 20px 0 20px'), false, '', '', null),
        ];
        $specs = [
            'Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Block')
                ->primitive('headline', 'string', default: 'Default headline')
                ->declared('product', SalesChannelProductEntity::class)
                ->build(),
            'Sw:Grid:Container' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Grid:Container', 'Grid Container')
                ->declared('padding', ['string', 'object'], properties: $nestedProperties)
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return new LayoutDefaultSeeder($registry, new StoredDefaultProvider());
    }
}
