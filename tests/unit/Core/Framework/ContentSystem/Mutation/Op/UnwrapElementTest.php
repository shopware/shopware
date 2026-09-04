<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\UnwrapElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UnwrapElement::class)]
class UnwrapElementTest extends TestCase
{
    #[TestDox('replaces the container with its slot children at the root')]
    public function testUnwrapReplacesContainerWithChildren(): void
    {
        $tree = new StoredTree([new StoredElement('container', 'Sw:Container', [], [], [
            'content' => [new StoredElement('a', 'Sw:Block'), new StoredElement('b', 'Sw:Block')],
        ])]);

        $result = (new UnwrapElement('container'))->apply($tree);

        static::assertSame(['a', 'b'], array_map(static fn (StoredElement $e): string => $e->id, $result->roots));
    }

    #[TestDox('hoists the children into the parent slot at the container position')]
    public function testUnwrapHoistsIntoParentSlotAtPosition(): void
    {
        $tree = new StoredTree([new StoredElement('parent', 'Sw:Block', [], [], [
            'content' => [
                new StoredElement('x', 'Sw:Block'),
                new StoredElement('container', 'Sw:Container', [], [], [
                    'items' => [new StoredElement('a', 'Sw:Block'), new StoredElement('b', 'Sw:Block')],
                ]),
                new StoredElement('y', 'Sw:Block'),
            ],
        ])]);

        $result = (new UnwrapElement('container'))->apply($tree);

        static::assertSame(['x', 'a', 'b', 'y'], array_map(static fn (StoredElement $e): string => $e->id, $result->roots[0]->slots['content']));
    }

    #[TestDox('reports the whole hoisted forest as affected, including grandchildren that lose the container scope')]
    public function testUnwrapAffectedAreHoistedSubtrees(): void
    {
        $tree = new StoredTree([new StoredElement('container', 'Sw:Container', [], [], [
            'content' => [
                new StoredElement('a', 'Sw:Block', [], [], [
                    'inner' => [new StoredElement('grandchild', 'Sw:Block')],
                ]),
                new StoredElement('b', 'Sw:Block'),
            ],
        ])]);

        $unwrap = new UnwrapElement('container');
        $unwrap->apply($tree);

        static::assertSame(['a', 'grandchild', 'b'], $unwrap->affected());
    }

    #[TestDox('reports the removed containers own static properties and consumed wiring, not its provided context')]
    public function testUnwrapReportsContainerOwnConfig(): void
    {
        $container = new StoredElement(
            'container',
            'Sw:Container',
            ['hero' => new DataRequirement('hero', 'entity', new StubLoaderConfig())],
            ['title' => StoredValue::ofString('Section'), 'spacing' => StoredValue::ofInt(3)],
            ['content' => [new StoredElement('kid', 'Sw:Block')]],
            new ContextDefinitions(
                ['themeProvider' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                ['theme' => new ContextConsumer(ContextType::Single, true)],
            ),
        );

        $unwrap = new UnwrapElement('container');
        $unwrap->apply(new StoredTree([$container]));

        static::assertSame(
            ['title' => 'Section', 'spacing' => 3],
            array_map(static fn (StoredValue $value): mixed => $value->jsonSerialize(), $unwrap->droppedProperties())
        );
        static::assertSame(['hero', 'theme'], $unwrap->droppedWiring());
    }

    #[TestDox('flattens children across all container slots in slot order')]
    public function testUnwrapFlattensAllSlots(): void
    {
        $tree = new StoredTree([new StoredElement('container', 'Sw:Container', [], [], [
            'header' => [new StoredElement('a', 'Sw:Block')],
            'body' => [new StoredElement('b', 'Sw:Block')],
        ])]);

        $result = (new UnwrapElement('container'))->apply($tree);

        static::assertSame(['a', 'b'], array_map(static fn (StoredElement $e): string => $e->id, $result->roots));
    }

    #[TestDox('removes an empty container and hoists nothing')]
    public function testUnwrapEmptyContainerJustRemovesIt(): void
    {
        $tree = new StoredTree([new StoredElement('container', 'Sw:Container'), new StoredElement('keep', 'Sw:Block')]);

        $unwrap = new UnwrapElement('container');
        $result = $unwrap->apply($tree);

        static::assertSame(['keep'], array_map(static fn (StoredElement $e): string => $e->id, $result->roots));
        static::assertSame([], $unwrap->affected());
    }

    #[TestDox('rejects unwrapping a container absent from the tree with a 400')]
    public function testUnwrapMissingContainerRejected(): void
    {
        $unwrap = new UnwrapElement('ghost');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $unwrap->apply(new StoredTree([new StoredElement('other', 'Sw:Block')]));
    }
}
