<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Administration;
use Shopware\Core\System\Snippet\SnippetFileHandler;
use Shopware\Storefront\Storefront;

/**
 * @internal
 */
#[CoversClass(SnippetFileHandler::class)]
class SnippetFileHandlerTest extends TestCase
{
    public function testFindBundleSnippetFiles(): void
    {
        $handler = new SnippetFileHandler();
        $files = $handler->findBundleSnippetFiles(new Storefront());
        static::assertCount(2, $files);

        $files = $handler->findBundleSnippetFiles(new Administration());
        static::assertCount(0, $files);
    }
}
