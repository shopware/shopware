<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Components;

use Doctrine\DBAL\Connection;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Util\Filesystem as UtilFilesystem;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentHelper;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[CoversClass(TwigComponentHelper::class)]
class TwigComponentHelperTest extends TestCase
{
    private const PROJECT_DIR = '/project';

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    }

    public function testGetComponentsReturnsEmptyCollectionWhenNoBundlesOrApps(): void
    {
        $helper = new TwigComponentHelper(
            [],
            self::PROJECT_DIR,
            $this->createConnectionMock(),
            $this->createMock(SourceResolver::class),
            $this->filesystem,
        );

        static::assertCount(0, $helper->getComponents());
    }

    public function testGetComponentsReturnsPrecomputedBundleComponents(): void
    {
        $helper = new TwigComponentHelper(
            [
                ['name' => 'Button', 'namespace' => 'TestBundle', 'path' => '/some/path/Button.html.twig'],
                ['name' => 'Card', 'namespace' => 'TestBundle', 'path' => '/some/path/Card.html.twig'],
            ],
            self::PROJECT_DIR,
            $this->createConnectionMock(),
            $this->createMock(SourceResolver::class),
            $this->filesystem,
        );

        $components = $helper->getComponents();

        static::assertCount(2, $components);
        static::assertTrue($components->has('TestBundle:Button'));
        static::assertTrue($components->has('TestBundle:Card'));
    }

    public function testGetComponentsHandlesNestedBundleComponents(): void
    {
        $helper = new TwigComponentHelper(
            [
                ['name' => 'Forms:Input:Text', 'namespace' => 'TestBundle', 'path' => '/some/path/Forms/Input/Text.html.twig'],
            ],
            self::PROJECT_DIR,
            $this->createConnectionMock(),
            $this->createMock(SourceResolver::class),
            $this->filesystem,
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('TestBundle:Forms:Input:Text'));

        $component = $components->get('TestBundle:Forms:Input:Text');
        static::assertNotNull($component);
        static::assertSame('Forms:Input:Text', $component->name);
        static::assertSame('TestBundle', $component->namespace);
    }

    public function testGetComponentsHandlesMultipleBundles(): void
    {
        $helper = new TwigComponentHelper(
            [
                ['name' => 'Button', 'namespace' => 'Bundle1', 'path' => '/bundle1/Button.html.twig'],
                ['name' => 'Card', 'namespace' => 'Bundle2', 'path' => '/bundle2/Card.html.twig'],
            ],
            self::PROJECT_DIR,
            $this->createConnectionMock(),
            $this->createMock(SourceResolver::class),
            $this->filesystem,
        );

        $components = $helper->getComponents();

        static::assertCount(2, $components);
        static::assertTrue($components->has('Bundle1:Button'));
        static::assertTrue($components->has('Bundle2:Card'));
    }

    public function testGetComponentsHandlesStorefrontNamespace(): void
    {
        $helper = new TwigComponentHelper(
            [
                ['name' => 'Button', 'namespace' => 'Storefront', 'path' => '/storefront/Button.html.twig'],
            ],
            self::PROJECT_DIR,
            $this->createConnectionMock(),
            $this->createMock(SourceResolver::class),
            $this->filesystem,
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('Button'));
        static::assertFalse($components->has('Storefront:Button'));
    }

    /**
     * Regression test: an app whose component lives in a subdirectory
     */
    public function testGetComponentsFromAppInSubdirectoryHasCorrectComponentName(): void
    {
        $this->filesystem->write('app-root/Resources/views/components/Custom/Test.html.twig', '<div>Test</div>');

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['namespace' => 'TestApp']]);

        $appFilesystem = $this->createMock(UtilFilesystem::class);
        $appFilesystem->method('has')->with(TwigComponentHelper::COMPONENT_DIRECTORY)->willReturn(true);
        $appFilesystem->method('path')->with(TwigComponentHelper::COMPONENT_DIRECTORY)->willReturn(
            Path::join(self::PROJECT_DIR, 'app-root', TwigComponentHelper::COMPONENT_DIRECTORY)
        );

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForAppName')
            ->with('TestApp')
            ->willReturn($appFilesystem);

        $helper = new TwigComponentHelper(
            [],
            self::PROJECT_DIR,
            $connection,
            $sourceResolver,
            $this->filesystem,
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('TestApp:Custom:Test'), 'Component should be named "Custom:Test", not just "Test"');
        static::assertFalse($components->has('TestApp:Test'), 'Component must not be named without its subdirectory');

        $component = $components->get('TestApp:Custom:Test');
        static::assertNotNull($component);
        static::assertSame('Custom:Test', $component->name);
        static::assertSame('TestApp', $component->namespace);
        static::assertSame('TestApp/Custom', $component->getRelativeNamespaceDirectory());
    }

    public function testGetComponentsFromAppWithMultipleTemplatesRegistersRootDirOnce(): void
    {
        $this->filesystem->write('app-root/Resources/views/components/Custom/Test.html.twig', '<div>Test</div>');
        $this->filesystem->write('app-root/Resources/views/components/Other/Widget.html.twig', '<div>Widget</div>');

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['namespace' => 'MultiTemplateApp']]);

        $appFilesystem = $this->createMock(UtilFilesystem::class);
        $appFilesystem->method('has')->with(TwigComponentHelper::COMPONENT_DIRECTORY)->willReturn(true);
        $appFilesystem->method('path')->with(TwigComponentHelper::COMPONENT_DIRECTORY)->willReturn(
            Path::join(self::PROJECT_DIR, 'app-root', TwigComponentHelper::COMPONENT_DIRECTORY)
        );

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForAppName')
            ->with('MultiTemplateApp')
            ->willReturn($appFilesystem);

        $helper = new TwigComponentHelper(
            [],
            self::PROJECT_DIR,
            $connection,
            $sourceResolver,
            $this->filesystem,
        );

        $components = $helper->getComponents();

        static::assertCount(2, $components);
        static::assertTrue($components->has('MultiTemplateApp:Custom:Test'));
        static::assertTrue($components->has('MultiTemplateApp:Other:Widget'));
    }

    public function testAppComponentsExcludeFilesInUnderscorePrefixedDirectories(): void
    {
        $this->filesystem->write('app-root/Resources/views/components/Button.html.twig', '<button>');
        $this->filesystem->write('app-root/Resources/views/components/ui/_private/Internal.html.twig', '<div>');

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['namespace' => 'TestApp']]);

        $appFilesystem = $this->createMock(UtilFilesystem::class);
        $appFilesystem->method('has')->with(TwigComponentHelper::COMPONENT_DIRECTORY)->willReturn(true);
        $appFilesystem->method('path')->with(TwigComponentHelper::COMPONENT_DIRECTORY)->willReturn(
            Path::join(self::PROJECT_DIR, 'app-root', TwigComponentHelper::COMPONENT_DIRECTORY)
        );

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForAppName')->willReturn($appFilesystem);

        $helper = new TwigComponentHelper(
            [],
            self::PROJECT_DIR,
            $connection,
            $sourceResolver,
            $this->filesystem,
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('TestApp:Button'));
        static::assertFalse($components->has('TestApp:ui:_private:Internal'));
    }

    public function testGetComponentsSkipsAppWhenFilesystemThrows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['namespace' => 'BrokenApp']]);

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForAppName')
            ->with('BrokenApp')
            ->willThrowException(new \RuntimeException('Filesystem unavailable'));

        $helper = new TwigComponentHelper(
            [],
            self::PROJECT_DIR,
            $connection,
            $sourceResolver,
            $this->filesystem,
        );

        static::assertCount(0, $helper->getComponents());
    }

    public function testGetComponentsSkipsAppWhenComponentDirDoesNotExist(): void
    {
        $appFilesystem = $this->createMock(UtilFilesystem::class);
        $appFilesystem->method('has')->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['namespace' => 'AppWithNoComponents']]);

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForAppName')
            ->with('AppWithNoComponents')
            ->willReturn($appFilesystem);

        $helper = new TwigComponentHelper(
            [],
            self::PROJECT_DIR,
            $connection,
            $sourceResolver,
            $this->filesystem,
        );

        static::assertCount(0, $helper->getComponents());
    }

    public function testGetComponentNameFromPathStripsComponentsPrefix(): void
    {
        $name = TwigComponentHelper::getComponentNameFromPath('components/Sw/Button.html.twig');

        static::assertSame('Sw:Button', $name);
    }

    public function testGetComponentsSkipsAppWhenListContentsThrows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['namespace' => 'TestApp']]);

        $appFilesystem = $this->createMock(UtilFilesystem::class);
        $appFilesystem->method('has')->with(TwigComponentHelper::COMPONENT_DIRECTORY)->willReturn(true);
        $appFilesystem->method('path')->willReturn(
            Path::join(self::PROJECT_DIR, 'app-root', TwigComponentHelper::COMPONENT_DIRECTORY)
        );

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForAppName')->willReturn($appFilesystem);

        $localFilesystem = $this->createMock(FilesystemOperator::class);
        $localFilesystem->method('directoryExists')->willReturn(true);
        $localFilesystem->method('listContents')->willThrowException(new \RuntimeException('Filesystem error'));

        $helper = new TwigComponentHelper(
            [],
            self::PROJECT_DIR,
            $connection,
            $sourceResolver,
            $localFilesystem,
        );

        static::assertCount(0, $helper->getComponents());
    }

    public function testGetComponentsSkipsNonFileAttributesItems(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['namespace' => 'TestApp']]);

        $appFilesystem = $this->createMock(UtilFilesystem::class);
        $appFilesystem->method('has')->with(TwigComponentHelper::COMPONENT_DIRECTORY)->willReturn(true);
        $appFilesystem->method('path')->willReturn(
            Path::join(self::PROJECT_DIR, 'app-root', TwigComponentHelper::COMPONENT_DIRECTORY)
        );

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForAppName')->willReturn($appFilesystem);

        $relativeDir = 'app-root/Resources/views/components';
        $localFilesystem = $this->createMock(FilesystemOperator::class);
        $localFilesystem->method('directoryExists')->willReturn(true);
        $localFilesystem->method('listContents')->willReturn(
            new DirectoryListing([
                new DirectoryAttributes($relativeDir . '/Subfolder'),
                new FileAttributes($relativeDir . '/Button.html.twig'),
            ])
        );

        $helper = new TwigComponentHelper(
            [],
            self::PROJECT_DIR,
            $connection,
            $sourceResolver,
            $localFilesystem,
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('TestApp:Button'));
    }

    public function testGetComponentsSkipsAppWhenRelativeDirIsOutsideProjectDir(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['namespace' => 'TestApp']]);

        $appFilesystem = $this->createMock(UtilFilesystem::class);
        $appFilesystem->method('has')->with(TwigComponentHelper::COMPONENT_DIRECTORY)->willReturn(true);
        $appFilesystem->method('path')->willReturn('/outside/project/components/');

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForAppName')->willReturn($appFilesystem);

        $helper = new TwigComponentHelper(
            [],
            self::PROJECT_DIR,
            $connection,
            $sourceResolver,
            $this->filesystem,
        );

        static::assertCount(0, $helper->getComponents());
    }

    public function testGetComponentsSkipsAppWhenLocalDirectoryDoesNotExist(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['namespace' => 'TestApp']]);

        $appFilesystem = $this->createMock(UtilFilesystem::class);
        $appFilesystem->method('has')->with(TwigComponentHelper::COMPONENT_DIRECTORY)->willReturn(true);
        // Points inside project dir, but no files are written there → directoryExists returns false
        $appFilesystem->method('path')->willReturn(
            Path::join(self::PROJECT_DIR, 'nonexistent-app', TwigComponentHelper::COMPONENT_DIRECTORY)
        );

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForAppName')->willReturn($appFilesystem);

        $helper = new TwigComponentHelper(
            [],
            self::PROJECT_DIR,
            $connection,
            $sourceResolver,
            $this->filesystem,
        );

        static::assertCount(0, $helper->getComponents());
    }

    private function createConnectionMock(): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        return $connection;
    }
}
