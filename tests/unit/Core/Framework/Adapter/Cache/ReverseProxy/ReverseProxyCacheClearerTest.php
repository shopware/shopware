<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\ReverseProxy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyCacheClearer;

/**
 * @internal
 */
#[CoversClass(ReverseProxyCacheClearer::class)]
class ReverseProxyCacheClearerTest extends TestCase
{
    public function testClear(): void
    {
        $gateway = $this->createMock(AbstractReverseProxyGateway::class);
        $gateway->expects(static::once())
            ->method('banAll');

        $clearer = new ReverseProxyCacheClearer($gateway);
        $clearer->clear('noop');
    }
}
