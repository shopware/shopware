<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Shopware\Core\Framework\ContentSystem\Layout\Type\PrimitiveDefaultProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;

/**
 * @internal
 */
#[CoversClass(LayoutDefaultSeeder::class)]
class LayoutDefaultSeederTest extends TestCase
{
    #[TestDox('seeds a missing primitive default and ignores reference properties on a content element')]
    public function testSeedsPrimitiveDefaultIgnoringReferences(): void
    {
        $element = new ContentElement('el', 'Sw:Block');

        $this->seeder()->seed([$element]);

        static::assertSame(['headline' => 'Default headline'], $element->getProperties());
    }

    #[TestDox('does not overwrite an authored primitive value on a content element')]
    public function testKeepsAuthoredValue(): void
    {
        $element = new ContentElement('el', 'Sw:Block', [], ['headline' => 'Authored']);

        $this->seeder()->seed([$element]);

        static::assertSame('Authored', $element->getProperty('headline'));
    }

    #[TestDox('seeds primitive defaults on slot descendants')]
    public function testSeedsSlotDescendants(): void
    {
        $child = new ContentElement('child', 'Sw:Block');
        $root = new ContentElement('root', 'Sw:Block', [], [], ['content' => new SlotContent([$child])]);

        $this->seeder()->seed([$root]);

        static::assertSame('Default headline', $child->getProperty('headline'));
    }

    #[TestDox('leaves a node whose component type is not registered untouched')]
    public function testNoOpsOnUnregisteredComponent(): void
    {
        $element = new ContentElement('el', 'Sw:Unregistered');

        $this->seeder()->seed([$element]);

        static::assertSame([], $element->getProperties());
    }

    #[TestDox('seeds a missing primitive default into a raw element array and recurses raw slots')]
    public function testSeedsRawArrayNodesAndRecursesSlots(): void
    {
        $forest = [[
            'id' => 'root',
            'component' => 'Sw:Block',
            'properties' => [],
            'slots' => [
                'content' => [
                    ['id' => 'child', 'component' => 'Sw:Block', 'properties' => []],
                ],
            ],
        ]];

        $expected = [[
            'id' => 'root',
            'component' => 'Sw:Block',
            'properties' => ['headline' => 'Default headline'],
            'slots' => [
                'content' => [
                    ['id' => 'child', 'component' => 'Sw:Block', 'properties' => ['headline' => 'Default headline']],
                ],
            ],
        ]];

        static::assertSame($expected, $this->seeder()->seed($forest));
    }

    #[TestDox('leaves a malformed scalar properties value untouched (no silent transform)')]
    public function testSeedRawArrayLeavesScalarPropertiesUntouched(): void
    {
        $forest = [['id' => 'el', 'component' => 'Sw:Block', 'properties' => 'oops']];

        static::assertSame($forest, $this->seeder()->seed($forest));
    }

    #[TestDox('leaves a malformed list-shaped properties value untouched rather than mixing key types')]
    public function testSeedRawArrayLeavesListShapedPropertiesUntouched(): void
    {
        $forest = [['id' => 'el', 'component' => 'Sw:Block', 'properties' => ['first', 'second']]];

        static::assertSame($forest, $this->seeder()->seed($forest));
    }

    #[TestDox('leaves a raw node without a string component untouched')]
    public function testSeedRawArrayLeavesNonStringComponentUntouched(): void
    {
        $forest = [['id' => 'el', 'slots' => []]];

        static::assertSame($forest, $this->seeder()->seed($forest));
    }

    #[TestDox('does not add a properties key to a registered component that has no primitive defaults')]
    public function testSeedRawArrayAddsNoPropertiesKeyWhenTypeHasNoDefaults(): void
    {
        $forest = [['id' => 'el', 'component' => 'Sw:NoDefaults']];

        static::assertSame($forest, $this->seeder()->seed($forest));
    }

    #[TestDox('leaves a raw slot whose value is not a list untouched')]
    public function testSeedRawArrayLeavesNonListSlotValueUntouched(): void
    {
        $forest = [['id' => 'el', 'component' => 'Sw:NoDefaults', 'slots' => ['content' => 'not-a-list']]];

        static::assertSame($forest, $this->seeder()->seed($forest));
    }

    private function seeder(): LayoutDefaultSeeder
    {
        $specs = [
            'Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Block')
                ->primitive('headline', 'string', default: 'Default headline')
                ->reference('product', SalesChannelProductEntity::class)
                ->build(),
            'Sw:NoDefaults' => ContentSystemElementTypeSpecificationBuilder::create('Sw:NoDefaults')
                ->primitive('label', 'string')
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return new LayoutDefaultSeeder($registry, new PrimitiveDefaultProvider());
    }
}
