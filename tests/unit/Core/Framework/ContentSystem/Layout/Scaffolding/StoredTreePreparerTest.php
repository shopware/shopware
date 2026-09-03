<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Scaffolding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\StoredTreePreparer;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Output\ElementTreePruner;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\ContentSystem\Output\SubTreeExtractor;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\ContentSystem\DataLoader\LanguageLoaderConfig;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredTreePreparer::class)]
class StoredTreePreparerTest extends TestCase
{
    #[TestDox('substitutes a declared token in a string property')]
    public function testPrepareResolvesStringProperties(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', 'Product {{productId}}')
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1']);

        static::assertSame('Product prod-1', $prepared[0]->property('title')?->asString());
    }

    #[TestDox('substitutes tokens in slot children at every depth')]
    public function testPrepareRecursesIntoSlotChildren(): void
    {
        $grandchild = StoredElementBuilder::create('text', 'grandchild-id')
            ->withProperty('title', 'Deep {{productId}}')
            ->build();
        $child = StoredElementBuilder::create('section', 'child-id')
            ->withSlot('default', [$grandchild])
            ->build();
        $root = StoredElementBuilder::create('section', 'root-id')
            ->withSlot('default', [$child])
            ->build();

        $prepared = $this->prepare([$root], ['productId' => 'prod-1']);

        $preparedGrandchild = $prepared[0]->slots['default'][0]->slots['default'][0];
        static::assertSame('Deep prod-1', $preparedGrandchild->property('title')?->asString());
    }

    #[TestDox('prunes away the sibling of the addressed target element while preserving the discarded subtree in the pre-prune forest')]
    public function testPreparePrunesAndPreservesPrePruneForest(): void
    {
        $prepared = $this->preparer()->prepare(
            [$this->targetAndSiblingRoot()],
            $this->targetedSpecification('target-id'),
            RenderingMode::SKELETON
        );

        // The target consumes context, so the prune keeps its ancestor for the data flow and drops the
        // sibling only; the pipeline's partial extract removes that ancestor after hydration.
        static::assertSame(['root-id', 'target-id'], $this->collectIds($prepared->tree));
        static::assertSame('target-id', $prepared->scaffolding->extractTargetId);
        // The sibling is what the prune drops. Wiring validation runs on this forest, so a defect in a
        // discarded subtree still has something to be judged against.
        static::assertSame(['root-id', 'target-id', 'sibling-id'], $this->collectIds($prepared->prePruneForest));
    }

    /**
     * @param scalar|null $value
     */
    #[DataProvider('nonStringPropertyProvider')]
    #[TestDox('leaves a $_dataName property untouched')]
    public function testPrepareLeavesNonStringPropertiesUntouched(string|int|float|bool|null $value): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('value', $value)
            ->build();

        $prepared = $this->prepare([$element], ['value' => 'substituted']);

        static::assertSame($value, $prepared[0]->property('value')?->jsonSerialize());
    }

    /**
     * @return array<string, array{scalar|null}>
     */
    public static function nonStringPropertyProvider(): array
    {
        return [
            'int' => [42],
            'float' => [4.2],
            'bool' => [true],
            'null' => [null],
        ];
    }

    #[TestDox('leaves the roots unwrapped when the specification carries no page-level data requirement')]
    public function testPrepareLeavesTheRootsUnwrappedWithoutPageLevelDataRequirements(): void
    {
        $root = StoredElementBuilder::create('section', 'root-id')->build();

        $prepared = $this->preparer()->prepare([$root], $this->specification([]), RenderingMode::SKELETON);

        static::assertSame([$root], $prepared->tree);
        static::assertFalse($prepared->scaffolding->virtualRootSurvivedPrune);
    }

    #[TestDox('returns the tree unchanged in SKELETON mode')]
    public function testPrepareResolvesNothingInSkeletonMode(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', 'Product {{productId}}')
            ->build();

        $prepared = $this->preparer()->prepare(
            [$element],
            $this->specification(['productId' => 'prod-1']),
            RenderingMode::SKELETON
        );

        static::assertSame([$element], $prepared->tree);
    }

    #[TestDox('wraps the roots in a virtual root and carries the wrapped forest as the pre-prune forest for page-level data requirements')]
    public function testPrepareWrapsRootAndCarriesForestForPageLevelDataRequirements(): void
    {
        $root = StoredElementBuilder::create('section', 'root-id')->build();

        $prepared = $this->preparer()->prepare([$root], $this->pageContextSpecification(), RenderingMode::SKELETON);

        // The wrap runs before the prune, so the wrapper is part of what validation judges.
        static::assertCount(1, $prepared->tree);
        static::assertSame(VirtualRootWrapper::VIRTUAL_ROOT_ID, $prepared->tree[0]->id);
        static::assertCount(1, $prepared->prePruneForest);
        static::assertSame(VirtualRootWrapper::VIRTUAL_ROOT_ID, $prepared->prePruneForest[0]->id);
        static::assertTrue($prepared->scaffolding->virtualRootSurvivedPrune);
    }

    #[TestDox('leaves a list property untouched, its string items included')]
    public function testPrepareDoesNotRecurseIntoListProperties(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('tags', ['Product {{productId}}', 'plain'])
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1']);

        static::assertSame(
            ['Product {{productId}}', 'plain'],
            $prepared[0]->property('tags')?->jsonSerialize()
        );
    }

    #[TestDox('leaves a map property untouched, its string values included')]
    public function testPrepareDoesNotRecurseIntoMapProperties(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('labels', ['headline' => 'Product {{productId}}'])
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1']);

        static::assertSame(
            ['headline' => 'Product {{productId}}'],
            $prepared[0]->property('labels')?->jsonSerialize()
        );
    }

    #[TestDox('leaves the element style untouched')]
    public function testPrepareLeavesStyleUntouched(): void
    {
        $style = new ElementStyle(['align' => ['sm' => 'left']]);
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', '{{productId}}')
            ->withStyle($style)
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1']);

        static::assertSame($style, $prepared[0]->style);
    }

    #[TestDox('leaves a token with no declared value verbatim')]
    public function testPrepareLeavesUnknownTokenVerbatim(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', 'Category {{categoryId}}')
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1']);

        static::assertSame('Category {{categoryId}}', $prepared[0]->property('title')?->asString());
    }

    #[TestDox('records that the virtual root did not survive a prune that cut it away')]
    public function testPrepareRecordsAVirtualRootThePruneRemoved(): void
    {
        $target = StoredElementBuilder::create('text', 'target-id')->build();
        $root = StoredElementBuilder::create('section', 'root-id')
            ->withSlot('default', [$target])
            ->build();
        $specification = new RenderingSpecification(
            [new DataRequirement('language', 'language', new LanguageLoaderConfig())],
            PlaceholderValues::from([]),
            new Request(),
            'target-id'
        );

        // Fixture guard: the target needs no parent data, so the prune really does cut above it rather
        // than there never having been a virtual root to lose.
        static::assertTrue((new VirtualRootWrapper())->requiresWrapping($specification, [$root]));
        static::assertFalse((new ContextDependencyAnalyzer())->requiresParentData($target));

        $prepared = $this->preparer()->prepare([$root], $specification, RenderingMode::SKELETON);

        static::assertSame(['target-id'], $this->collectIds($prepared->tree));
        static::assertFalse($prepared->scaffolding->virtualRootSurvivedPrune);
    }

    #[TestDox('treats an empty target element id as no partial render at all')]
    public function testPrepareTreatsAnEmptyTargetElementIdAsNoTarget(): void
    {
        $root = $this->targetAndSiblingRoot();

        $prepared = $this->preparer()->prepare([$root], $this->targetedSpecification(''), RenderingMode::SKELETON);

        static::assertNull($prepared->scaffolding->extractTargetId);
        static::assertSame([$root], $prepared->tree);
    }

    #[TestDox('leaves an empty tree of roots empty')]
    public function testPrepareHandlesAnEmptyTreeOfRoots(): void
    {
        $prepared = $this->prepare([], ['productId' => 'prod-1']);

        static::assertSame([], $prepared);
    }

    #[TestDox('leaves a token verbatim when the placeholder values map is empty')]
    public function testPrepareLeavesATokenVerbatimWithAnEmptyPlaceholderValuesMap(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', 'Product {{productId}}')
            ->build();

        $prepared = $this->prepare([$element], []);

        static::assertSame('Product {{productId}}', $prepared[0]->property('title')?->asString());
    }

    private function preparer(): StoredTreePreparer
    {
        return new StoredTreePreparer(
            new VirtualRootWrapper(),
            new PartialRenderer(new ElementTreePruner(), new ContextDependencyAnalyzer(), new SubTreeExtractor()),
        );
    }

    private function targetAndSiblingRoot(): StoredElement
    {
        return StoredElementBuilder::create('section', 'root-id')
            ->withSlot('default', [
                StoredElementBuilder::create('text', 'target-id')
                    ->withConsumer('product', ContextType::Single)
                    ->build(),
                StoredElementBuilder::create('text', 'sibling-id')->build(),
            ])
            ->build();
    }

    /**
     * @param list<StoredElement> $tree
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function collectIds(array $tree, array $ids = []): array
    {
        foreach ($tree as $element) {
            $ids[] = $element->id;
            foreach ($element->slots as $children) {
                $ids = $this->collectIds($children, $ids);
            }
        }

        return $ids;
    }

    /**
     * @param list<StoredElement> $tree
     * @param array<string, string|int|bool|float> $placeholderValues
     *
     * @return list<StoredElement>
     */
    private function prepare(array $tree, array $placeholderValues): array
    {
        return $this->preparer()->prepare($tree, $this->specification($placeholderValues), RenderingMode::FULL)->tree;
    }

    /**
     * @param array<string, string|int|bool|float> $placeholderValues
     */
    private function specification(array $placeholderValues): RenderingSpecification
    {
        return new RenderingSpecification([], PlaceholderValues::from($placeholderValues), new Request());
    }

    private function pageContextSpecification(): RenderingSpecification
    {
        return new RenderingSpecification(
            [new DataRequirement('language', 'language', new LanguageLoaderConfig())],
            PlaceholderValues::from([]),
            new Request()
        );
    }

    private function targetedSpecification(string $targetElementId): RenderingSpecification
    {
        return new RenderingSpecification([], PlaceholderValues::from([]), new Request(), $targetElementId);
    }
}
