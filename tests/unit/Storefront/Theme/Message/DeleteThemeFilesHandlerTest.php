<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Message;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\Message\DeleteThemeFilesHandler;
use Shopware\Storefront\Theme\Message\DeleteThemeFilesMessage;

/**
 * @internal
 */
#[CoversClass(DeleteThemeFilesHandler::class)]
class DeleteThemeFilesHandlerTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    #[IgnoreDeprecations]
    public function testFilesAreDeletedIfPathIsCurrentlyNotActive(): void
    {
        $this->expectUserDeprecationMessage('Method "Shopware\\Storefront\\Theme\\Message\\DeleteThemeFilesHandler::__invoke()" is deprecated and will be removed in v6.8.0.0.');
        $this->expectUserDeprecationMessage('Method "Shopware\\Storefront\\Theme\\Message\\DeleteThemeFilesMessage::getSalesChannelId()" is deprecated and will be removed in v6.8.0.0.');
        $this->expectUserDeprecationMessage('Method "Shopware\\Storefront\\Theme\\Message\\DeleteThemeFilesMessage::getThemeId()" is deprecated and will be removed in v6.8.0.0.');
        $this->expectUserDeprecationMessage('Method "Shopware\\Storefront\\Theme\\Message\\DeleteThemeFilesMessage::getThemePath()" is deprecated and will be removed in v6.8.0.0.');

        $currentPath = 'path';

        $message = new DeleteThemeFilesMessage($currentPath, 'salesChannel', 'theme');

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('deleteDirectory')->with('theme' . \DIRECTORY_SEPARATOR . $currentPath);

        $handler = new DeleteThemeFilesHandler(
            $filesystem,
            // the path builder will generate a different path then the hard coded one
            new MD5ThemePathBuilder(),
        );

        $handler($message);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[IgnoreDeprecations]
    public function testFilesAreNotDeletedIfPathIsCurrentlyActive(): void
    {
        $this->expectUserDeprecationMessage('Method "Shopware\\Storefront\\Theme\\Message\\DeleteThemeFilesHandler::__invoke()" is deprecated and will be removed in v6.8.0.0.');
        $this->expectUserDeprecationMessage('Method "Shopware\\Storefront\\Theme\\Message\\DeleteThemeFilesMessage::getSalesChannelId()" is deprecated and will be removed in v6.8.0.0.');
        $this->expectUserDeprecationMessage('Method "Shopware\\Storefront\\Theme\\Message\\DeleteThemeFilesMessage::getThemeId()" is deprecated and will be removed in v6.8.0.0.');
        $this->expectUserDeprecationMessage('Method "Shopware\\Storefront\\Theme\\Message\\DeleteThemeFilesMessage::getThemePath()" is deprecated and will be removed in v6.8.0.0.');

        $pathBuilder = new MD5ThemePathBuilder();

        $currentPath = $pathBuilder->assemblePath('salesChannel', 'theme');

        $message = new DeleteThemeFilesMessage($currentPath, 'salesChannel', 'theme');

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->never())->method('deleteDirectory');

        $handler = new DeleteThemeFilesHandler(
            $filesystem,
            $pathBuilder,
        );

        $handler($message);
    }
}
