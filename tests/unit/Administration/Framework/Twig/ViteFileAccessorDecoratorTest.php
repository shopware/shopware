<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\Twig;

use League\Flysystem\Filesystem as FlysystemFilesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Pentatrion\ViteBundle\Service\FileAccessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\Twig\ViteFileAccessorDecorator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Framework\BundleFixture;
use Shopware\Core\Test\Stub\Symfony\StubKernel;
use Symfony\Component\Asset\Package as AssetPackage;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\Bundle as SymfonyBundle;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ViteFileAccessorDecorator::class)]
class ViteFileAccessorDecoratorTest extends TestCase
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $configs = [
        '_default' => [
            'base' => 'bundles/administration/',
        ],
    ];

    private Stub&AssetPackage $packageMock;

    private ViteFileAccessorDecorator $decorator;

    protected function setUp(): void
    {
        $kernel = new StubKernel([
            new BundleFixture('Administration', __DIR__ . '/Fixtures/Administration'),
            new BundleFixture('TestBundle', __DIR__ . '/Fixtures/TestBundle'),
        ]);

        $this->packageMock = static::createStub(UrlPackage::class);
        $this->packageMock->method('getUrl')
            ->willReturn('https:://shopware.com');

        $this->decorator = new ViteFileAccessorDecorator(
            $this->configs,
            $this->packageMock,
            $kernel,
            new Filesystem(),
            new FlysystemFilesystem(new InMemoryFilesystemAdapter()),
            new TagAwareAdapter(new ArrayAdapter()),
        );
    }

    #[DataProvider('hasFileProvider')]
    public function testHasFile(string $configName, string $fileType, bool $fileExists): void
    {
        static::assertSame($fileExists, $this->decorator->hasFile($configName, $fileType));
    }

    #[DataProvider('getDataProvider')]
    public function testGetData(bool $pullFromCache, string $configName, string $bundleName, string $expectedAssetUrl): void
    {
        if ($pullFromCache) {
            $this->decorator->getData($configName, FileAccessor::ENTRYPOINTS);
        }

        $result = $this->decorator->getData($configName, FileAccessor::ENTRYPOINTS);

        static::assertSame($expectedAssetUrl, $result['entryPoints'][$bundleName]['js'][0]);
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function hasFileProvider(): iterable
    {
        yield 'has file default file accessor entrypoints true' => [
            '_default',
            FileAccessor::ENTRYPOINTS,
            true,
        ];
        yield 'has file default file accessor manifest true' => [
            '_default',
            FileAccessor::MANIFEST,
            true,
        ];
        yield 'has file test bundle file accessor entrypoints true' => [
            'TestBundle',
            FileAccessor::ENTRYPOINTS,
            true,
        ];
        yield 'has file test bundle file accessor manifest true' => [
            'TestBundle',
            FileAccessor::MANIFEST,
            true,
        ];
        yield 'has file invalid file accessor manifest false' => [
            'invalid',
            FileAccessor::MANIFEST,
            false,
        ];
        yield 'has file invalid file accessor entrypoints false' => [
            'invalid',
            FileAccessor::ENTRYPOINTS,
            false,
        ];
        yield 'has file invalid false' => [
            'invalid',
            '',
            false,
        ];
    }

    /**
     * @return iterable<string, array{bool, string, string, string}>
     */
    public static function getDataProvider(): iterable
    {
        yield 'provider false default administration https shopware com bundles administration' => [
            false,
            '_default',
            'administration',
            'https:://shopware.com/bundles/administration/administration/assets/app.js',
        ];
        yield 'provider true default administration https shopware com bundles administration' => [
            true,
            '_default',
            'administration',
            'https:://shopware.com/bundles/administration/administration/assets/app.js',
        ];
        yield 'provider false test bundle test bundle https shopware com bundles test' => [
            false,
            'TestBundle',
            'test-bundle',
            'https:://shopware.com/bundles/test/administration/assets/app.js',
        ];
        yield 'provider true test bundle test bundle https shopware com bundles test' => [
            true,
            'TestBundle',
            'test-bundle',
            'https:://shopware.com/bundles/test/administration/assets/app.js',
        ];
    }

    public function testGetBundleDataReturnsEntrypoints(): void
    {
        $bundle = new BundleFixture('Administration', __DIR__ . '/Fixtures/Administration');

        $result = $this->decorator->getBundleData($bundle);

        static::assertArrayHasKey('entryPoints', $result);
    }

    public function testGetDataReturnsEmptyArrayForPlainSymfonyBundle(): void
    {
        // plain Symfony bundle (not ShopwareBundle) always returns []
        $kernel = new StubKernel([
            new BundleFixture('Administration', __DIR__ . '/Fixtures/Administration'),
            new PlainSymfonyBundle('PlainBundle', __DIR__ . '/Fixtures/Administration'),
        ]);

        $decorator = new ViteFileAccessorDecorator(
            $this->configs,
            $this->packageMock,
            $kernel,
            new Filesystem(),
            new FlysystemFilesystem(new InMemoryFilesystemAdapter()),
            new TagAwareAdapter(new ArrayAdapter()),
        );

        static::assertSame([], $decorator->getData('PlainBundle', ViteFileAccessorDecorator::ENTRYPOINTS));
    }

    public function testGetDataReturnsEmptyArrayWhenViteFileIsMissing(): void
    {
        // ShopwareBundle with no vite file returns []
        $kernel = new StubKernel([
            new BundleFixture('NoViteBundle', __DIR__ . '/Fixtures'),
        ]);

        $decorator = new ViteFileAccessorDecorator(
            $this->configs,
            $this->packageMock,
            $kernel,
            new Filesystem(),
            new FlysystemFilesystem(new InMemoryFilesystemAdapter()),
            new TagAwareAdapter(new ArrayAdapter()),
        );

        static::assertSame([], $decorator->getData('NoViteBundle', ViteFileAccessorDecorator::ENTRYPOINTS));
    }

    public function testReadsManifestFromAssetFilesystemWhenLocalFileIsMissing(): void
    {
        $assetFilesystem = $this->remoteFilesystem('bundles/administration/administration/.vite/entrypoints.json');
        $decorator = $this->createDecorator($assetFilesystem, new BundleFixture('Administration', __DIR__ . '/Fixtures/NoLocal'));

        static::assertTrue($decorator->hasFile('_default', FileAccessor::ENTRYPOINTS));
        static::assertSame(
            'https:://shopware.com/bundles/administration/administration/assets/app.js',
            $decorator->getData('_default', FileAccessor::ENTRYPOINTS)['entryPoints']['administration']['js'][0]
        );
    }

    public function testBundleSuffixIsStrippedInRemoteKey(): void
    {
        $assetFilesystem = $this->remoteFilesystem('bundles/test/administration/.vite/entrypoints.json');
        $decorator = $this->createDecorator($assetFilesystem, new BundleFixture('TestBundle', __DIR__ . '/Fixtures/NoLocal'));

        static::assertTrue($decorator->hasFile('TestBundle', FileAccessor::ENTRYPOINTS));
    }

    public function testLocalFileWinsOverRemote(): void
    {
        $assetFilesystem = new FlysystemFilesystem(new InMemoryFilesystemAdapter());
        $assetFilesystem->write('bundles/administration/administration/.vite/entrypoints.json', '{"entryPoints":{"administration":{"js":["/remote.js"]}}}');
        $decorator = $this->createDecorator($assetFilesystem, new BundleFixture('Administration', __DIR__ . '/Fixtures/Administration'));

        static::assertSame(
            'https:://shopware.com/bundles/administration/administration/assets/app.js',
            $decorator->getData('_default', FileAccessor::ENTRYPOINTS)['entryPoints']['administration']['js'][0]
        );
    }

    public function testNeitherLocalNorRemoteFileExists(): void
    {
        $decorator = $this->createDecorator(
            new FlysystemFilesystem(new InMemoryFilesystemAdapter()),
            new BundleFixture('Administration', __DIR__ . '/Fixtures/NoLocal')
        );

        static::assertFalse($decorator->hasFile('_default', FileAccessor::ENTRYPOINTS));
        static::assertSame([], $decorator->getData('_default', FileAccessor::ENTRYPOINTS));
    }

    public function testFilesystemExceptionIsHandled(): void
    {
        $assetFilesystem = static::createStub(FilesystemOperator::class);
        $assetFilesystem->method('fileExists')->willThrowException(new class('storage down') extends \RuntimeException implements FilesystemException {
        });
        $decorator = $this->createDecorator($assetFilesystem, new BundleFixture('Administration', __DIR__ . '/Fixtures/NoLocal'));

        static::assertFalse($decorator->hasFile('_default', FileAccessor::ENTRYPOINTS));
        static::assertSame([], $decorator->getData('_default', FileAccessor::ENTRYPOINTS));
    }

    public function testManifestIsReadOnlyOncePerBundleAndFileType(): void
    {
        $assetFilesystem = $this->createMock(FilesystemOperator::class);
        $assetFilesystem->expects($this->once())->method('fileExists')->willReturn(false);
        $decorator = $this->createDecorator($assetFilesystem, new BundleFixture('Administration', __DIR__ . '/Fixtures/NoLocal'));

        static::assertFalse($decorator->hasFile('_default', FileAccessor::ENTRYPOINTS));
        static::assertFalse($decorator->hasFile('_default', FileAccessor::ENTRYPOINTS));
        static::assertSame([], $decorator->getData('_default', FileAccessor::ENTRYPOINTS));
    }

    public function testRemoteManifestIsServedFromCacheForNextInstance(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $bundle = new BundleFixture('Administration', __DIR__ . '/Fixtures/NoLocal');
        $this->createDecorator($this->remoteFilesystem('bundles/administration/administration/.vite/entrypoints.json'), $bundle, $cache)
            ->getData('_default', FileAccessor::ENTRYPOINTS);

        $assetFilesystem = $this->createMock(FilesystemOperator::class);
        $assetFilesystem->expects($this->never())->method('fileExists');
        $assetFilesystem->expects($this->never())->method('read');

        $data = $this->createDecorator($assetFilesystem, $bundle, $cache)->getData('_default', FileAccessor::ENTRYPOINTS);

        static::assertArrayHasKey('entryPoints', $data);
    }

    public function testMissingRemoteManifestIsServedFromCacheForNextInstance(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $bundle = new BundleFixture('Administration', __DIR__ . '/Fixtures/NoLocal');
        $this->createDecorator(new FlysystemFilesystem(new InMemoryFilesystemAdapter()), $bundle, $cache)
            ->hasFile('_default', FileAccessor::ENTRYPOINTS);

        $assetFilesystem = $this->createMock(FilesystemOperator::class);
        $assetFilesystem->expects($this->never())->method('fileExists');

        static::assertFalse($this->createDecorator($assetFilesystem, $bundle, $cache)->hasFile('_default', FileAccessor::ENTRYPOINTS));
    }

    public function testAssetMetaDataTagInvalidationReadsRemoteAgain(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $bundle = new BundleFixture('Administration', __DIR__ . '/Fixtures/NoLocal');
        $empty = new FlysystemFilesystem(new InMemoryFilesystemAdapter());
        static::assertFalse($this->createDecorator($empty, $bundle, $cache)->hasFile('_default', FileAccessor::ENTRYPOINTS));
        $cache->commit();

        $filled = $this->remoteFilesystem('bundles/administration/administration/.vite/entrypoints.json');
        static::assertFalse($this->createDecorator($filled, $bundle, $cache)->hasFile('_default', FileAccessor::ENTRYPOINTS));

        $cache->invalidateTags(['asset-metaData']);

        static::assertTrue($this->createDecorator($filled, $bundle, $cache)->hasFile('_default', FileAccessor::ENTRYPOINTS));
    }

    public function testDifferentAssetUrlReadsRemoteAgain(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $bundle = new BundleFixture('Administration', __DIR__ . '/Fixtures/NoLocal');
        $this->createDecorator(new FlysystemFilesystem(new InMemoryFilesystemAdapter()), $bundle, $cache)
            ->hasFile('_default', FileAccessor::ENTRYPOINTS);
        $cache->commit();

        $otherPackage = static::createStub(UrlPackage::class);
        $otherPackage->method('getUrl')->willReturn('https:://other.example');
        $assetFilesystem = $this->createMock(FilesystemOperator::class);
        $assetFilesystem->expects($this->once())->method('fileExists')->willReturn(false);

        $this->createDecorator($assetFilesystem, $bundle, $cache, $otherPackage)->hasFile('_default', FileAccessor::ENTRYPOINTS);
    }

    public function testFilesystemExceptionIsNotCached(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $bundle = new BundleFixture('Administration', __DIR__ . '/Fixtures/NoLocal');
        $failing = static::createStub(FilesystemOperator::class);
        $failing->method('fileExists')->willThrowException(new class('storage down') extends \RuntimeException implements FilesystemException {
        });
        static::assertFalse($this->createDecorator($failing, $bundle, $cache)->hasFile('_default', FileAccessor::ENTRYPOINTS));
        $cache->commit();

        $filled = $this->remoteFilesystem('bundles/administration/administration/.vite/entrypoints.json');

        static::assertTrue($this->createDecorator($filled, $bundle, $cache)->hasFile('_default', FileAccessor::ENTRYPOINTS));
    }

    private function remoteFilesystem(string $path): FilesystemOperator
    {
        $filesystem = new FlysystemFilesystem(new InMemoryFilesystemAdapter());
        $filesystem->write($path, (string) file_get_contents(__DIR__ . '/Fixtures/Administration/Resources/public/administration/.vite/entrypoints.json'));

        return $filesystem;
    }

    private function createDecorator(
        FilesystemOperator $assetFilesystem,
        BundleFixture $bundle,
        ?TagAwareAdapter $cache = null,
        ?AssetPackage $package = null,
    ): ViteFileAccessorDecorator {
        return new ViteFileAccessorDecorator(
            $this->configs,
            $package ?? $this->packageMock,
            new StubKernel([$bundle]),
            new Filesystem(),
            $assetFilesystem,
            $cache ?? new TagAwareAdapter(new ArrayAdapter()),
        );
    }
}

/**
 * @internal
 */
class PlainSymfonyBundle extends SymfonyBundle
{
    public function __construct(string $name, string $path)
    {
        $this->name = $name;
        $this->path = $path;
    }
}
