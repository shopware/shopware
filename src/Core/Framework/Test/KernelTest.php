<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Kernel;

class KernelTest extends TestCase
{
    use KernelTestBehaviour;

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

    public function testDatabaseTimeZonesAreEqual(): void
    {
        $env = (bool) EnvironmentHelper::getVariable('SHOPWARE_DBAL_TIMEZONE_SUPPORT_ENABLED', false);

        if ($env === false) {
            static::markTestSkipped('Database does not support timezones');
        }

        $c = $this->getContainer()->get(Connection::class);

        static::assertSame(
            $c->fetchOne('SELECT @@session.time_zone'),
            date_default_timezone_get()
        );
    }
}
