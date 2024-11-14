<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\Twig;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\Twig\ViteFileAccessorDecorator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Asset\Packages;

/**
 * @internal
 */
#[Package('administration')]
#[CoversClass(ViteFileAccessorDecorator::class)]
class ViteFileAccessorDecoratorTest extends TestCase
{
    private MockObject $filesystemMock;

    /**
     * @var array<string, array<string, string>>
     */
    private array $configs = [
        '_default' => [
            'base' => 'bundles/administration/',
        ],
    ];

    private MockObject $packagesMock;

    private ViteFileAccessorDecorator $decorator;

    protected function setUp(): void
    {
        $this->filesystemMock = $this->createMock(FilesystemOperator::class);
        $this->packagesMock = $this->createMock(Packages::class);
        $this->packagesMock->method('getUrl')
            ->willReturn('https:://shopware.com/bundles/administration/');

        $this->decorator = new ViteFileAccessorDecorator(
            $this->configs,
            $this->filesystemMock,
            $this->packagesMock,
        );
    }

    #[DataProvider('hasFileProvider')]
    public function testHasFile(string $configName, string $fileType, string $filePath, bool $fileExists): void
    {
        $this->filesystemMock->expects(static::once())
            ->method('has')
            ->with($filePath)
            ->willReturn($fileExists);

        $result = $this->decorator->hasFile($configName, $fileType);
        static::assertEquals($fileExists, $result);
    }

    #[DataProvider('getDataProvider')]
    public function testGetData(bool $pullFromCache): void
    {
        $this->filesystemMock->expects(static::once())
            ->method('read')
            ->with('bundles/administration/.vite/entrypoints.json')
            ->willReturn('{"entryPoints":{"administration":{"app":["app.js"]}}}');

        if ($pullFromCache) {
            $this->decorator->getData('_default', ViteFileAccessorDecorator::ENTRYPOINTS);
        }
        $result = $this->decorator->getData('_default', ViteFileAccessorDecorator::ENTRYPOINTS);
        static::assertArrayHasKey('entryPoints', $result);
        static::assertArrayHasKey('administration', $result['entryPoints']);
        static::assertArrayHasKey('app', $result['entryPoints']['administration']);
        static::assertEquals('https:://shopware.com/bundles/administration/app.js', $result['entryPoints']['administration']['app'][0]);
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
                'bundles/administration/.vite/entrypoints.json',
                true,
            ],
            [
                '_default',
                ViteFileAccessorDecorator::MANIFEST,
                'bundles/administration/.vite/manifest.json',
                true,
            ],
            [
                'invalid',
                ViteFileAccessorDecorator::MANIFEST,
                '.vite/manifest.json',
                false,
            ],
            [
                'invalid',
                ViteFileAccessorDecorator::ENTRYPOINTS,
                '.vite/entrypoints.json',
                false,
            ],
            [
                'invalid',
                'no_file_path',
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
