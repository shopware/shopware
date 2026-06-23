<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutDefaultMaterializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;

/**
 * @internal
 */
#[CoversClass(LayoutDefaultMaterializer::class)]
class LayoutDefaultMaterializerTest extends TestCase
{
    #[TestDox('seeds a missing primitive default and ignores reference properties on a content element')]
    public function testSeedsPrimitiveDefaultIgnoringReferences(): void
    {
        $element = new ContentElement('el', 'Sw:Block');

        $this->materializer()->materialize([$element]);

        static::assertSame(['headline' => 'Default headline'], $element->getProperties());
    }

    #[TestDox('does not overwrite an authored primitive value on a content element')]
    public function testKeepsAuthoredValue(): void
    {
        $element = new ContentElement('el', 'Sw:Block', [], ['headline' => 'Authored']);

        $this->materializer()->materialize([$element]);

        static::assertSame('Authored', $element->getProperty('headline'));
    }

    #[TestDox('seeds primitive defaults on slot descendants')]
    public function testSeedsSlotDescendants(): void
    {
        $child = new ContentElement('child', 'Sw:Block');
        $root = new ContentElement('root', 'Sw:Block', [], [], ['content' => new SlotContent([$child])]);

        $this->materializer()->materialize([$root]);

        static::assertSame('Default headline', $child->getProperty('headline'));
    }

    #[TestDox('leaves a node whose component type is not registered untouched')]
    public function testNoOpsOnUnregisteredComponent(): void
    {
        $element = new ContentElement('el', 'Sw:Unregistered');

        $this->materializer()->materialize([$element]);

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

        static::assertSame($expected, $this->materializer()->materialize($forest));
    }

    private function materializer(): LayoutDefaultMaterializer
    {
        $specs = [
            'Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Block')
                ->primitive('headline', 'string', default: 'Default headline')
                ->reference('product', SalesChannelProductEntity::class)
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return new LayoutDefaultMaterializer($registry);
    }
}
