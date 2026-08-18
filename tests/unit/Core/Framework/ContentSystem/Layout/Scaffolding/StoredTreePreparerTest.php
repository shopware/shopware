<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Scaffolding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\StoredTreePreparer;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
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

    /**
     * @param scalar|null $value
     */
    #[DataProvider('nonStringPropertyProvider')]
    #[TestDox('leaves a $variant property untouched')]
    public function testPrepareLeavesNonStringPropertiesUntouched(string $variant, string|int|float|bool|null $value): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('value', $value)
            ->build();

        $prepared = $this->prepare([$element], ['value' => 'substituted']);

        static::assertSame($value, $prepared[0]->property('value')?->jsonSerialize());
    }

    /**
     * @return array<string, array{string, scalar|null}>
     */
    public static function nonStringPropertyProvider(): array
    {
        return [
            'int' => ['int', 42],
            'float' => ['float', 4.2],
            'bool' => ['bool', true],
            'null' => ['null', null],
        ];
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

    #[TestDox('returns the tree unchanged in SKELETON mode')]
    public function testPrepareResolvesNothingInSkeletonMode(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', 'Product {{productId}}')
            ->build();

        $prepared = (new StoredTreePreparer())->prepare(
            [$element],
            $this->specification(['productId' => 'prod-1']),
            RenderingMode::SKELETON
        );

        static::assertSame([$element], $prepared);
    }

    /**
     * @param list<StoredElement> $tree
     * @param array<string, string|int|bool|float> $placeholderValues
     *
     * @return list<StoredElement>
     */
    private function prepare(array $tree, array $placeholderValues): array
    {
        return (new StoredTreePreparer())->prepare($tree, $this->specification($placeholderValues), RenderingMode::FULL);
    }

    /**
     * @param array<string, string|int|bool|float> $placeholderValues
     */
    private function specification(array $placeholderValues): RenderingSpecification
    {
        return new RenderingSpecification([], PlaceholderValues::from($placeholderValues), new Request());
    }
}
