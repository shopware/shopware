<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Output\SubTreeExtractor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\RenderedElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SubTreeExtractor::class)]
class SubTreeExtractorTest extends TestCase
{
    private SubTreeExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new SubTreeExtractor();
    }

    #[TestDox('returns the root instance itself when the root element matches the target ID')]
    public function testExtractReturnsTheRootInstanceWhenTargetIsRootElement(): void
    {
        $root = RenderedElementBuilder::create('section', 'root-id')->build();

        $result = $this->extractor->extract($root, 'root-id');

        // Identity, not a copy: handing the found instance back is safe because `RenderedElement` is
        // `final readonly`, so a caller cannot mutate what the rest of the tree still points at.
        static::assertSame($root, $result);
    }

    #[TestDox('returns the nested instance itself for an element found in a child slot')]
    public function testExtractNested(): void
    {
        $child = RenderedElementBuilder::create('text', 'child-id')->build();
        $root = RenderedElementBuilder::create('section', 'root-id')
            ->withSlot('default', [$child])
            ->build();

        $result = $this->extractor->extract($root, 'child-id');

        // Identity, not a copy: handing the found instance back is safe because `RenderedElement` is
        // `final readonly`, so a caller cannot mutate what the rest of the tree still points at.
        static::assertSame($child, $result);
    }

    #[TestDox('returns null when the target element is not found in the tree')]
    public function testExtractReturnsNullWhenElementNotFound(): void
    {
        $root = RenderedElementBuilder::create('section', 'root-id')->build();

        $result = $this->extractor->extract($root, 'missing-id');

        static::assertNull($result);
    }
}
