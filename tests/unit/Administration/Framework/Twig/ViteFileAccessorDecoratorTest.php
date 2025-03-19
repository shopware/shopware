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
            ->willReturn('https:://shopware.com/bundles/administration/');

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
    public function testGetData(bool $pullFromCache): void
    {
        if ($pullFromCache) {
            $this->decorator->getData('_default', ViteFileAccessorDecorator::ENTRYPOINTS);
        }

        $result = $this->decorator->getData('_default', ViteFileAccessorDecorator::ENTRYPOINTS);
        static::assertArrayHasKey('entryPoints', $result);
        static::assertArrayHasKey('administration', $result['entryPoints']);
        static::assertArrayHasKey('app', $result['entryPoints']['administration']);
        static::assertSame('https:://shopware.com/bundles/administration/app.js', $result['entryPoints']['administration']['app'][0]);
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
                false,
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
     * @return array<int, array<int, bool>>
     */
    public static function getDataProvider(): array
    {
        return [
            [
                false,
            ],
            [
                true,
            ],
        ];
    }
}
