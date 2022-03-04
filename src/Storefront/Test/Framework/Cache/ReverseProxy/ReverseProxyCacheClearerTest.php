<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Cache\ReverseProxy;

use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCacheClearer;

class ReverseProxyCacheClearerTest extends TestCase
{
    public function testClear(): void
    {
        $gateway = $this->createMock(AbstractReverseProxyGateway::class);
        $gateway->expects(static::once())->method('ban')->with(['/']);
        $clearer = new ReverseProxyCacheClearer($gateway, ['/']);
        $clearer->clear('noop');
    }
}
