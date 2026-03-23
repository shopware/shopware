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

    /**
     * @param array{'entryPoints', string, 'js', 0} $assetKeys
     */
    #[DataProvider('getDataProvider')]
    public function testGetData(bool $pullFromCache, string $configName, array $assetKeys, string $expectedAssetUrl): void
    {
        if ($pullFromCache) {
            $this->decorator->getData($configName, FileAccessor::ENTRYPOINTS);
        }

        $result = $this->decorator->getData($configName, FileAccessor::ENTRYPOINTS);

        // Dynamically check the keys
        $firstArrayKey = array_shift($assetKeys);
        $previousValue = $result[$firstArrayKey];
        foreach ($assetKeys as $key) {
            // Use the previous collected value to check the next key
            static::assertArrayHasKey($key, $previousValue);
            $previousValue = $previousValue[$key];
        }

        // Check that the last key value is the expected asset URL
        static::assertSame($expectedAssetUrl, $previousValue);
    }

    /**
     * @return list<array{string, string, bool}>
     */
    public static function hasFileProvider(): array
    {
        return [
            [
                '_default',
                FileAccessor::ENTRYPOINTS,
                true,
            ],
            [
                '_default',
                FileAccessor::MANIFEST,
                true,
            ],
            [
                'TestBundle',
                FileAccessor::ENTRYPOINTS,
                true,
            ],
            [
                'TestBundle',
                FileAccessor::MANIFEST,
                true,
            ],
            [
                'invalid',
                FileAccessor::MANIFEST,
                false,
            ],
            [
                'invalid',
                FileAccessor::ENTRYPOINTS,
                false,
            ],
            [
                'invalid',
                '',
                false,
            ],
        ];
    }

    /**
     * @return list<array{bool, string, array{'entryPoints', string, 'js', 0}, string}>
     */
    public static function getDataProvider(): array
    {
        return [
            [
                false,
                '_default',
                [
                    'entryPoints',
                    'administration',
                    'js',
                    0,
                ],
                'https:://shopware.com/bundles/administration/administration/assets/app.js',
            ],
            [
                true,
                '_default',
                [
                    'entryPoints',
                    'administration',
                    'js',
                    0,
                ],
                'https:://shopware.com/bundles/administration/administration/assets/app.js',
            ],
            [
                false,
                'TestBundle',
                [
                    'entryPoints',
                    'test-bundle',
                    'js',
                    0,
                ],
                'https:://shopware.com/bundles/test/administration/assets/app.js',
            ],
            [
                true,
                'TestBundle',
                [
                    'entryPoints',
                    'test-bundle',
                    'js',
                    0,
                ],
                'https:://shopware.com/bundles/test/administration/assets/app.js',
            ],
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
