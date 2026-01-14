<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class KernelTest extends TestCase
{
    use KernelTestBehaviour;

    public function testUTCIsAlwaysSetToDatabase(): void
    {
        $c = static::getContainer()->get(Connection::class);

        static::assertSame($c->fetchOne('SELECT @@session.time_zone'), '+00:00');
    }

    /**
     * Symfony's http_cache service must NOT exist in the container.
     *
     * Shopware uses its own HttpCacheKernel (registered as http_kernel.cache decorator).
     * If Symfony's http_cache service exists, it would bypass Shopware's caching layer
     * and break the request handling flow.
     *
     * This also ensures long runner compatibility (RoadRunner, FrankenPHP, Swoole):
     * Without http_cache, Symfony Kernel::handle() properly manages requestStackSize
     * and resetServices flags, which are required for service reset between requests.
     *
     * @see \Symfony\Component\HttpKernel\Kernel::handle()
     * @see \Shopware\Core\Framework\Adapter\Kernel\HttpCacheKernel
     */
    public function testSymfonyHttpCacheServiceDoesNotExist(): void
    {
        $container = static::getContainer();

        static::assertFalse(
            $container->has('http_cache'),
            'Symfony http_cache service must NOT exist. '
            . 'Shopware uses its own HttpCacheKernel (http_kernel.cache decorator). '
            . 'If http_cache exists, it breaks long runner support and bypasses Shopware caching.'
        );
    }

    /**
     * Shopware's HttpCacheKernel must be registered as a decorator of http_kernel.
     */
    public function testShopwareHttpCacheKernelIsRegistered(): void
    {
        $container = static::getContainer();

        static::assertTrue(
            $container->has('http_kernel'),
            'http_kernel service must exist'
        );

        $httpKernel = $container->get('http_kernel');

        static::assertInstanceOf(
            \Shopware\Core\Framework\Adapter\Kernel\HttpCacheKernel::class,
            $httpKernel,
            'http_kernel should be decorated by Shopware HttpCacheKernel'
        );
    }
}
