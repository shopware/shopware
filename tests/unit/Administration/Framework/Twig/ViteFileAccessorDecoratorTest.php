<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\Twig\ViteFileAccessorDecorator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Framework\BundleFixture;
use Shopware\Core\Test\Stub\Symfony\StubKernel;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Filesystem\Filesystem;

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

    private MockObject&\Symfony\Component\Asset\Package $packageMock;

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
     * @param list<string> $assetKeys
     */
    #[DataProvider('getDataProvider')]
    public function testGetData(bool $pullFromCache, string $configName, array $assetKeys, string $expectedAssetUrl): void
    {
        if ($pullFromCache) {
            $this->decorator->getData($configName, ViteFileAccessorDecorator::ENTRYPOINTS);
        }

        $result = $this->decorator->getData($configName, ViteFileAccessorDecorator::ENTRYPOINTS);

        // Dynamically check the keys
        $previousValue = null;
        foreach ($assetKeys as $key) {
            // First iteration: get value from service result
            if ($previousValue === null) {
                static::assertArrayHasKey($key, $result);
                $previousValue = $result[$key];
                continue;
            }

            // Use the previous collected value to check the next key
            static::assertArrayHasKey($key, $previousValue);
            $previousValue = $previousValue[$key];
        }

        // Check that the last key value is the expected asset URL
        static::assertSame($expectedAssetUrl, $previousValue);
    }

    /**
     * @return array<int, array<int, string|bool>>
     */
    public static function hasFileProvider(): array
    {
        return [
            [
                '_default',
                ViteFileAccessorDecorator::ENTRYPOINTS,
                true,
            ],
            [
                '_default',
                ViteFileAccessorDecorator::MANIFEST,
                true,
            ],
            [
                'TestBundle',
                ViteFileAccessorDecorator::ENTRYPOINTS,
                true,
            ],
            [
                'TestBundle',
                ViteFileAccessorDecorator::MANIFEST,
                true,
            ],
            [
                'invalid',
                ViteFileAccessorDecorator::MANIFEST,
                false,
            ],
            [
                'invalid',
                ViteFileAccessorDecorator::ENTRYPOINTS,
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
     * @return list<list<bool|string|list<string|int>>>
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
}
