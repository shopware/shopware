<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\DataTransfer\SnippetPath;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\SnippetPath\SnippetPath;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SnippetPath::class)]
class SnippetPathTest extends TestCase
{
    public function testLocalCanBeSet(): void
    {
        $snippetPathLocal = new SnippetPath('path/to/snippet', true);
        $snippetPathNonLocal = new SnippetPath('path/to/snippet', false);
        static::assertTrue($snippetPathLocal->isLocal);
        static::assertFalse($snippetPathNonLocal->isLocal);
    }
}
