<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Kernel;

class KernelTest extends TestCase
{
    /**
     * @dataProvider provideVersions
     */
    public function testItCreatesShopwareVersion(string $unparsedVersion, string $parsedVersion): void
    {
        $kernelPluginLoaderMock = $this->getMockBuilder(StaticKernelPluginLoader::class)
            ->disableOriginalConstructor()->getMock();

        $kernel = new Kernel(
            'dev',
            false,
            $kernelPluginLoaderMock,
            '',
            $unparsedVersion,
            null
        );

        $parsedShopwareVersion = ReflectionHelper::getPropertyValue($kernel, 'shopwareVersion');

        static::assertEquals($parsedVersion, $parsedShopwareVersion);
    }

    public function provideVersions(): array
    {
        return [
            [
                '6.1.1.12-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                '6.1.1.12-dev',
            ],
            [
                '6.10.10.x-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                '6.10.10.x-dev',
            ],
            [
                '6.3.1.x-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                '6.3.1.x-dev',
            ],
            [
                '6.3.1.1-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                '6.3.1.1-dev',
            ],
            [
                'v6.3.1.1-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                '6.3.1.1-dev',
            ],
            [
                '12.1.1.12-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                Kernel::SHOPWARE_FALLBACK_VERSION,
            ],
            [
                'v6.3.1.1',
                Kernel::SHOPWARE_FALLBACK_VERSION,
            ],
            [
                '6.2.1',
                Kernel::SHOPWARE_FALLBACK_VERSION,
            ],
            [
                'foobar',
                Kernel::SHOPWARE_FALLBACK_VERSION,
            ],
            [
                '1010806',
                Kernel::SHOPWARE_FALLBACK_VERSION,
            ],
        ];
    }
}
