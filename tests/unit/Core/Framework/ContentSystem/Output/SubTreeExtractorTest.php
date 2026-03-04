<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Output\SubTreeExtractor;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;

/**
 * @internal
 */
#[CoversClass(SubTreeExtractor::class)]
class SubTreeExtractorTest extends TestCase
{
    private SubTreeExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new SubTreeExtractor();
    }

    #[TestDox('returns a cloned subtree when the root element matches the target ID')]
    public function testExtractReturnsClonedRootWhenTargetIsRootElement(): void
    {
        $id = 'root-id';
        $root = ContentElementBuilder::create('section', $id)->build();

        $result = $this->extractor->extract($root, $id);

        static::assertNotNull($result);
        static::assertSame($id, $result->getId());
        static::assertNotSame($root, $result);
    }

    #[TestDox('finds and returns a cloned subtree for a nested element in child slots')]
    public function testExtractNested(): void
    {
        $childId = 'child-id';
        $child = ContentElementBuilder::create('text', $childId)->build();
        $root = ContentElementBuilder::create('section', 'root-id')
            ->withSlot('default', [$child])
            ->build();

        $result = $this->extractor->extract($root, $childId);

        static::assertNotNull($result);
        static::assertSame($childId, $result->getId());
        static::assertNotSame($child, $result);
    }

    #[TestDox('returns null when the target element is not found in the tree')]
    public function testExtractReturnsNullWhenElementNotFound(): void
    {
        $root = ContentElementBuilder::create('section', 'root-id')->build();

        $result = $this->extractor->extract($root, 'missing-id');

        static::assertNull($result);
    }
}
