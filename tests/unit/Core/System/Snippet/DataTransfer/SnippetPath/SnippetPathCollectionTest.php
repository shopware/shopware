<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\DataTransfer\SnippetPath;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\SnippetPath\SnippetPath;
use Shopware\Core\System\Snippet\DataTransfer\SnippetPath\SnippetPathCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SnippetPathCollection::class)]
class SnippetPathCollectionTest extends TestCase
{
    private SnippetPathCollection $snippetPathCollection;

    protected function setUp(): void
    {
        $this->snippetPathCollection = new SnippetPathCollection();
    }

    public function testElementsAreAddedWithKey(): void
    {
        static::assertTrue($this->snippetPathCollection->isEmpty());

        $snippetPath1 = new SnippetPath('path/to/snippet1');
        $snippetPath2 = new SnippetPath('path/to/snippet2');

        $this->snippetPathCollection->add($snippetPath1);
        $this->snippetPathCollection->add($snippetPath2);

        static::assertFalse($this->snippetPathCollection->isEmpty());

        $all = $this->snippetPathCollection->getElements();
        static::assertCount(2, $all);
        static::assertFalse(\array_is_list($all));
        static::assertArrayHasKey('path/to/snippet1', $all);
        static::assertArrayHasKey('path/to/snippet2', $all);
    }

    public function testPathsAreUnique(): void
    {
        $snippetPath1 = new SnippetPath('path/to/snippet1');
        $snippetPath1Duplicate = new SnippetPath('path/to/snippet1');

        $this->snippetPathCollection->add($snippetPath1);
        static::assertCount(1, $this->snippetPathCollection);

        $this->snippetPathCollection->add($snippetPath1Duplicate);
        static::assertCount(1, $this->snippetPathCollection);
    }

    public function testHasPathByLocationSuccessful(): void
    {
        $snippetPath1 = new SnippetPath('path/to/snippet1');
        $snippetPath2 = new SnippetPath('path/to/snippet2');
        $this->snippetPathCollection->add($snippetPath1);

        static::assertTrue($this->snippetPathCollection->hasPath($snippetPath1));
        static::assertFalse($this->snippetPathCollection->hasPath($snippetPath2));
    }

    public function testToLocationArray(): void
    {
        $snippetPath1 = new SnippetPath('path/to/snippet1');
        $snippetPath2 = new SnippetPath('path/to/snippet2');
        $this->snippetPathCollection->add($snippetPath1);
        $this->snippetPathCollection->add($snippetPath2);

        static::assertSame(
            [
                'path/to/snippet1',
                'path/to/snippet2',
            ],
            $this->snippetPathCollection->toLocationArray()
        );
    }
}
