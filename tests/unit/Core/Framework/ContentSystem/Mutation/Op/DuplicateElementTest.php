<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\DuplicateElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DuplicateElement::class)]
class DuplicateElementTest extends TestCase
{
    #[TestDox('inserts the clone as the next sibling with a fresh id')]
    public function testDuplicateInsertsCloneAsNextSibling(): void
    {
        $tree = new StoredTree([new StoredElement('original', 'Sw:Card'), new StoredElement('other', 'Sw:Block')]);

        $result = (new DuplicateElement('original'))->apply($tree);

        static::assertCount(3, $result->roots);
        static::assertSame('original', $result->roots[0]->id);
        static::assertSame('Sw:Card', $result->roots[1]->component);
        static::assertNotSame('original', $result->roots[1]->id);
        static::assertSame('other', $result->roots[2]->id);
    }

    #[TestDox('remints every id in the cloned subtree and reports them as affected')]
    public function testDuplicateRemintsEverySubtreeIdAndReportsThemAsAffected(): void
    {
        $tree = new StoredTree([new StoredElement('root', 'Sw:Block', [], [], [
            'content' => [new StoredElement('child', 'Sw:Block')],
        ])]);

        $duplicate = new DuplicateElement('root');
        $result = $duplicate->apply($tree);

        $clone = $result->roots[1];
        $clonedChild = $clone->slots['content'][0];
        static::assertNotSame('root', $clone->id);
        static::assertNotSame('child', $clonedChild->id);
        static::assertSame('Sw:Block', $clonedChild->component);
        static::assertSame([$clone->id, $clonedChild->id], $duplicate->affected());
    }

    #[TestDox('reports only the clone id as affected when the duplicated element has no children')]
    public function testDuplicateLeafAffectedIsCloneIdOnly(): void
    {
        $tree = new StoredTree([new StoredElement('original', 'Sw:Card'), new StoredElement('other', 'Sw:Block')]);

        $duplicate = new DuplicateElement('original');
        $result = $duplicate->apply($tree);

        $clone = $result->roots[1];
        static::assertNotSame('original', $clone->id);
        static::assertSame('original', $result->roots[0]->id);
        static::assertSame('Sw:Card', $clone->component);
        static::assertSame([], $clone->properties());
        static::assertSame([$clone->id], $duplicate->affected());
    }

    #[TestDox('carries key-based wiring, context definitions, and style over to the clone unchanged')]
    public function testDuplicatePreservesWiringAndStyle(): void
    {
        $requirement = new DataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $contextDefinitions = new ContextDefinitions(['list' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())], []);
        $style = new ElementStyle(['col-span' => ['md' => 6]]);
        $tree = new StoredTree([new StoredElement('original', 'Sw:Card', ['product' => $requirement], [], [], $contextDefinitions, $style)]);

        $result = (new DuplicateElement('original'))->apply($tree);

        static::assertSame(['product' => $requirement], $result->roots[1]->dataRequirements);
        static::assertSame($contextDefinitions, $result->roots[1]->contextDefinitions);
        static::assertSame($style->toArray(), $result->roots[1]->style->toArray());
    }

    #[TestDox('carries attributed specifications over to the reconstructed clone unchanged')]
    public function testDuplicatePreservesAttributedSpecificationsOnClone(): void
    {
        $original = StoredElementBuilder::create('Sw:Card', 'original')
            ->withAttributedSpecification('product', 'spec-1')
            ->build();
        $tree = new StoredTree([$original, new StoredElement('other', 'Sw:Block')]);

        $result = (new DuplicateElement('original'))->apply($tree);

        $clone = $result->roots[1];
        static::assertNotSame('original', $clone->id);
        static::assertSame(['product' => 'spec-1'], $clone->attributedSpecifications);
    }

    #[TestDox('duplicates a nested element into the same parent slot')]
    public function testDuplicateNestedElement(): void
    {
        $tree = new StoredTree([new StoredElement('parent', 'Sw:Block', [], [], [
            'content' => [new StoredElement('original', 'Sw:Card')],
        ])]);

        $result = (new DuplicateElement('original'))->apply($tree);

        $children = $result->roots[0]->slots['content'];
        static::assertCount(2, $children);
        static::assertSame('original', $children[0]->id);
        static::assertTrue(Uuid::isValid($children[1]->id));
        static::assertSame('Sw:Card', $children[1]->component);
    }

    #[TestDox('inserts the clone at an explicit index when given')]
    public function testDuplicateAtExplicitIndex(): void
    {
        $tree = new StoredTree([new StoredElement('original', 'Sw:Card'), new StoredElement('other', 'Sw:Block')]);

        $result = (new DuplicateElement('original', 0))->apply($tree);

        static::assertSame('Sw:Card', $result->roots[0]->component);
        static::assertSame('original', $result->roots[1]->id);
        static::assertSame('other', $result->roots[2]->id);
    }

    #[TestDox('rejects duplicating an element absent from the tree with a 400')]
    public function testDuplicateMissingElementRejected(): void
    {
        $duplicate = new DuplicateElement('ghost');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $duplicate->apply(new StoredTree([new StoredElement('other', 'Sw:Block')]));
    }
}
