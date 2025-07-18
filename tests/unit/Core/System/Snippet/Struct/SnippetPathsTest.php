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
    public function testSnippetPathsDto(): void
    {
        $paths = new SnippetPaths();
        static::assertTrue($paths->empty());

        $paths->add('path/to/snippet1');
        $paths->add('path/to/snippet2');

        $paths->merge([
            'path/to/snippet3',
            'path/to/snippet4',
        ]);

        $all = $paths->all();
        static::assertCount(4, $all);
        static::assertEquals([
            'path/to/snippet1',
            'path/to/snippet2',
            'path/to/snippet3',
            'path/to/snippet4',
        ], $all);
    }
}
