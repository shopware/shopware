<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Message;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\Message\DeleteThemeFilesHandler;
use Shopware\Storefront\Theme\Message\DeleteThemeFilesMessage;

/**
 * @internal
 */
#[CoversClass(DeleteThemeFilesHandler::class)]
class DeleteThemeFilesHandlerTest extends TestCase
{
    public function testFilesAreDeletedIfPathIsCurrentlyNotActive(): void
    {
        $currentPath = 'path';

        $message = new DeleteThemeFilesMessage($currentPath, 'salesChannel', 'theme');

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(static::once())->method('deleteDirectory')->with('theme' . \DIRECTORY_SEPARATOR . $currentPath);

        $handler = new DeleteThemeFilesHandler(
            $filesystem,
            // the path builder will generate a different path then the hard coded one
            new MD5ThemePathBuilder()
        );

        $handler($message);
    }

    public function testFilesAreNotDeletedIfPathIsCurrentlyActive(): void
    {
        $pathBuilder = new MD5ThemePathBuilder();

        $currentPath = $pathBuilder->assemblePath('salesChannel', 'theme');

        $message = new DeleteThemeFilesMessage($currentPath, 'salesChannel', 'theme');

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(static::never())->method('deleteDirectory');

        $handler = new DeleteThemeFilesHandler(
            $filesystem,
            $pathBuilder
        );

        $handler($message);
    }
}
