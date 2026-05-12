<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\Twig;

use Pentatrion\ViteBundle\Service\FileAccessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\Twig\ViteFileAccessorDecorator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Framework\BundleFixture;
use Shopware\Core\Test\Stub\Symfony\StubKernel;
use Symfony\Component\Asset\Package as AssetPackage;
use Symfony\Component\Asset\UrlPackage;
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

    private MockObject&AssetPackage $packageMock;

    private ViteFileAccessorDecorator $decorator;

    protected function setUp(): void
    {
        $kernel = new StubKernel([
            new BundleFixture('Administration', __DIR__ . '/Fixtures/Administration'),
            new BundleFixture('TestBundle', __DIR__ . '/Fixtures/TestBundle'),
        ]);

        $this->packageMock = $this->createMock(UrlPackage::class);
        $this->packageMock->method('getUrl')
            ->willReturn('https:://shopware.com');

        $this->decorator = new ViteFileAccessorDecorator(
            $this->configs,
            $this->packageMock,
            $kernel,
            new Filesystem(),
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
        );

        static::assertSame([], $decorator->getData('NoViteBundle', ViteFileAccessorDecorator::ENTRYPOINTS));
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
