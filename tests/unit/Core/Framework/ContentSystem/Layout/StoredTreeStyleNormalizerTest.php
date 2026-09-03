<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\BoxSpacingNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTreeStyleNormalizer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredTreeStyleNormalizer::class)]
class StoredTreeStyleNormalizerTest extends TestCase
{
    private const BREAKPOINT_AWARE = 'display';

    private const EXPANDED = ['xs' => false, 'sm' => true, 'md' => true, 'lg' => true, 'xl' => true, 'xxl' => true];

    #[TestDox('canonicalises the style of a root element against the option registry')]
    public function testNormalizesARootStyle(): void
    {
        $root = StoredElementBuilder::create('Sw:Block', 'el-1')
            ->withStyle(new ElementStyle([self::BREAKPOINT_AWARE => ['xs' => false]]))
            ->build();

        $result = $this->normalizer()->normalize(new StoredTree([$root]));

        static::assertSame([self::BREAKPOINT_AWARE => self::EXPANDED], $result->roots[0]->style->toArray());
        static::assertSame(Breakpoint::values(), array_keys($result->roots[0]->style->toArray()[self::BREAKPOINT_AWARE]));
    }

    #[TestDox('canonicalises the style of an element nested two slots deep, not only of the roots')]
    public function testNormalizesEveryDepth(): void
    {
        $grandchild = StoredElementBuilder::create('Sw:Block', 'grandchild-1')
            ->withStyle(new ElementStyle([self::BREAKPOINT_AWARE => ['xs' => false]]))
            ->build();

        $child = StoredElementBuilder::create('Sw:Block', 'child-1')->withSlot('inner', [$grandchild])->build();
        $root = StoredElementBuilder::create('Sw:Block', 'el-1')->withSlot('content', [$child])->build();

        $result = $this->normalizer()->normalize(new StoredTree([$root]));

        static::assertSame(
            [self::BREAKPOINT_AWARE => self::EXPANDED],
            $result->roots[0]->slots['content'][0]->slots['inner'][0]->style->toArray()
        );
    }

    #[TestDox('returns an already normalised forest unchanged')]
    public function testIsIdempotent(): void
    {
        $root = StoredElementBuilder::create('Sw:Block', 'el-1')
            ->withStyle(new ElementStyle([self::BREAKPOINT_AWARE => ['xs' => false]]))
            ->build();

        $normalizer = $this->normalizer();

        $once = $normalizer->normalize(new StoredTree([$root]));
        $twice = $normalizer->normalize($once);

        static::assertSame([self::BREAKPOINT_AWARE => self::EXPANDED], $twice->roots[0]->style->toArray());
        static::assertSame($once->roots[0]->style->toArray(), $twice->roots[0]->style->toArray());
    }

    #[TestDox('hands back a new forest and leaves the one it was given untouched')]
    public function testDoesNotMutateTheForestItWasGiven(): void
    {
        $root = StoredElementBuilder::create('Sw:Block', 'el-1')
            ->withStyle(new ElementStyle([self::BREAKPOINT_AWARE => ['xs' => false]]))
            ->build();

        $tree = new StoredTree([$root]);

        $this->normalizer()->normalize($tree);

        static::assertSame([self::BREAKPOINT_AWARE => ['xs' => false]], $tree->roots[0]->style->toArray());
    }

    #[TestDox('leaves everything that is not style alone, seeding no default and reconciling no attribution')]
    public function testTouchesNothingButStyle(): void
    {
        $root = StoredElementBuilder::create('Sw:Block', 'el-1')
            ->withProperty('headline', 'authored')
            ->withAttributedSpecification('media', 'core:media-picker')
            ->withStyle(new ElementStyle([self::BREAKPOINT_AWARE => ['xs' => false]]))
            ->build();

        $result = $this->normalizer()->normalize(new StoredTree([$root]));

        static::assertSame(['headline'], array_keys($result->roots[0]->properties()));
        static::assertSame(['media' => 'core:media-picker'], $result->roots[0]->attributedSpecifications);
    }

    /**
     * A registry holding one breakpoint-aware boolean option with a declared default, so a partial
     * breakpoint map has something to expand from.
     */
    private function normalizer(): StoredTreeStyleNormalizer
    {
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([
            self::BREAKPOINT_AWARE => new StyleOptionSpecification(
                self::BREAKPOINT_AWARE,
                new StyleOptionValueType(StyleOptionValueType::TYPE_BOOLEAN, null, null, null, true),
                true,
                null,
                'test',
            ),
        ]);

        return new StoredTreeStyleNormalizer(new ElementStyleNormalizer($registry, new BoxSpacingNormalizer()));
    }
}
