<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Struct\SnippetPaths;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SnippetPaths::class)]
class SnippetPathsTest extends TestCase
{
    private SnippetPaths $snippetPaths;

    protected function setUp(): void
    {
        $this->snippetPaths = new SnippetPaths();
    }

    public function testPathsAreMerged(): void
    {
        static::assertTrue($this->snippetPaths->isEmpty());

        $this->snippetPaths->add('path/to/snippet1');
        $this->snippetPaths->add('path/to/snippet2');

        static::assertFalse($this->snippetPaths->isEmpty());

        // Check also that duplicates are not merged.
        $this->snippetPaths->merge([
            'path/to/snippet3',
            'path/to/snippet4',
            'path/to/snippet4',
            'path/to/snippet4',
        ]);

        $all = $this->snippetPaths->all();
        static::assertCount(4, $all);
        static::assertEquals([
            'path/to/snippet1',
            'path/to/snippet2',
            'path/to/snippet3',
            'path/to/snippet4',
        ], $all);
    }

    public function testPathsAreUnique(): void
    {
        $this->snippetPaths->add('path/to/snippet1');
        static::assertCount(1, $this->snippetPaths);

        $this->snippetPaths->add('path/to/snippet1');
        static::assertCount(1, $this->snippetPaths);
    }

    public function testHasPathCheck(): void
    {
        $this->snippetPaths->add('path/to/snippet1');
        static::assertTrue($this->snippetPaths->has('path/to/snippet1'));
    }

    public function testPathsAreCounted(): void
    {
        $this->snippetPaths->add('path/to/snippet1');
        $this->snippetPaths->add('path/to/snippet2');
        $this->snippetPaths->add('path/to/snippet3');
        static::assertCount(3, $this->snippetPaths);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed. {@see testPathsAreMerged()} covers the new behaviour.
     */
    public function testDeprecatedEmptyMethodBehavior(): void
    {
        // Test that deprecated method behaves same as new method
        static::assertTrue($this->snippetPaths->empty());

        $this->snippetPaths->add('path/to/snippet1');
        static::assertFalse($this->snippetPaths->empty());
    }
}
