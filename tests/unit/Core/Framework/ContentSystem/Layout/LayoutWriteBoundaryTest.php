<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\BoxSpacingNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteBoundary;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTreeStyleNormalizer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutWriteBoundary::class)]
class LayoutWriteBoundaryTest extends TestCase
{
    private const BREAKPOINT_AWARE = 'display';

    private const FLAT = 'test-flat-span';

    #[TestDox('expands a partially specified breakpoint map from the option default')]
    public function testStyleNormalizationExpandsAPartialBreakpointMap(): void
    {
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
            ->withStyle(new ElementStyle([self::BREAKPOINT_AWARE => ['xs' => false]]))
            ->build();

        $result = $this->boundary()->apply(new StoredTree([$element]));

        static::assertSame(
            [self::BREAKPOINT_AWARE => ['xs' => false, 'sm' => true, 'md' => true, 'lg' => true, 'xl' => true, 'xxl' => true]],
            $result->roots[0]->style->toArray()
        );
        static::assertSame(Breakpoint::values(), array_keys($result->roots[0]->style->toArray()[self::BREAKPOINT_AWARE]));
    }

    #[TestDox('leaves an option declaring breakpointAware false as the flat scalar it was written as')]
    public function testStyleNormalizationLeavesAFlatOptionUnwrapped(): void
    {
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
            ->withStyle(new ElementStyle([self::FLAT => 7]))
            ->build();

        $result = $this->boundary()->apply(new StoredTree([$element]));

        static::assertSame([self::FLAT => 7], $result->roots[0]->style->toArray());
    }

    #[TestDox('normalizes the style of an element nested inside a slot, not only of the roots')]
    public function testStyleNormalizationReachesEveryDepth(): void
    {
        $child = StoredElementBuilder::create('Sw:Block', 'child-1')
            ->withStyle(new ElementStyle([self::BREAKPOINT_AWARE => ['xs' => false]]))
            ->build();

        $root = StoredElementBuilder::create('Sw:Block', 'el-1')->withSlot('content', [$child])->build();

        $result = $this->boundary()->apply(new StoredTree([$root]));

        static::assertSame(
            [self::BREAKPOINT_AWARE => ['xs' => false, 'sm' => true, 'md' => true, 'lg' => true, 'xl' => true, 'xxl' => true]],
            $result->roots[0]->slots['content'][0]->style->toArray()
        );
    }

    #[TestDox('hands back a new forest and leaves the one it was given untouched')]
    public function testApplyDoesNotMutateTheForestItWasGiven(): void
    {
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
            ->withStyle(new ElementStyle([self::BREAKPOINT_AWARE => ['xs' => false]]))
            ->build();

        $tree = new StoredTree([$element]);

        $this->boundary()->apply($tree);

        static::assertSame([self::BREAKPOINT_AWARE => ['xs' => false]], $tree->roots[0]->style->toArray());
    }

    #[TestDox('rejects a collaborator that hands back something other than a stored element')]
    public function testApplyRejectsANonElementNode(): void
    {
        $seeder = static::createStub(LayoutDefaultSeeder::class);
        $seeder->method('seed')->willReturn(['not-an-element']);

        $boundary = new LayoutWriteBoundary($seeder, new StoredTreeStyleNormalizer($this->styleNormalizer()), $this->passthroughReconciler());

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('layout', StoredElement::class, 'string')
        );

        $boundary->apply(new StoredTree([StoredElementBuilder::create('Sw:Block', 'el-1')->build()]));
    }

    private function boundary(): LayoutWriteBoundary
    {
        $seeder = static::createStub(LayoutDefaultSeeder::class);
        $seeder->method('seed')->willReturnArgument(0);

        return new LayoutWriteBoundary($seeder, new StoredTreeStyleNormalizer($this->styleNormalizer()), $this->passthroughReconciler());
    }

    /**
     * A registry holding exactly two options: a breakpoint-aware boolean with a declared default (the
     * expansion case) and a flat integer with none (the pass-through case).
     */
    private function styleNormalizer(): ElementStyleNormalizer
    {
        $options = [
            self::BREAKPOINT_AWARE => new StyleOptionSpecification(
                self::BREAKPOINT_AWARE,
                new StyleOptionValueType(StyleOptionValueType::TYPE_BOOLEAN, null, null, null, true),
                true,
                null,
                'test',
            ),
            self::FLAT => new StyleOptionSpecification(
                self::FLAT,
                new StyleOptionValueType(StyleOptionValueType::TYPE_INTEGER, null, null, null, null),
                false,
                null,
                'test',
            ),
        ];

        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn($options);

        return new ElementStyleNormalizer($registry, new BoxSpacingNormalizer());
    }

    private function passthroughReconciler(): AttributionReconciler
    {
        $reconciler = static::createStub(AttributionReconciler::class);
        $reconciler->method('reconcile')->willReturnArgument(0);

        return $reconciler;
    }
}
