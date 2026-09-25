<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\SnippetFileHandler;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SnippetFileHandler::class)]
class SnippetFileHandlerTest extends TestCase
{
    public function testFindsTheSnippetFilesOfAnExtensionBelowADirectory(): void
    {
        $handler = new SnippetFileHandler(new Filesystem());
        $extension = __DIR__ . '/_fixtures/ExtensionWithSnippets';
        $bundle = $extension . '/src/Sample/Resources';

        static::assertSame(
            [
                $bundle . '/app/administration/src/module/sample/snippet/de.json',
                $bundle . '/app/administration/src/module/sample/snippet/en.json',
            ],
            $handler->findAdministrationSnippetFilesBelow($extension),
        );
        static::assertSame(
            [
                $bundle . '/snippet/storefront.de.json',
                $bundle . '/snippet/storefront.en.json',
            ],
            $handler->findStorefrontSnippetFilesBelow($extension),
        );
    }

    public function testExistsReflectsTheFilesystem(): void
    {
        $handler = new SnippetFileHandler(new Filesystem());

        static::assertTrue($handler->exists(__FILE__));
        static::assertFalse($handler->exists(__DIR__ . '/does-not-exist.json'));
    }
}
