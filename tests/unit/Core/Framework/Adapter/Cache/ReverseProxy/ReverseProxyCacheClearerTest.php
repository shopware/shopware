<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\ReverseProxy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyCacheClearer;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(ReverseProxyCacheClearer::class)]
class ReverseProxyCacheClearerTest extends TestCase
{
    #[DisabledFeatures(['v6.7.0.0'])]
    public function testClear(): void
    {
        $gateway = $this->createMock(AbstractReverseProxyGateway::class);
        $gateway->expects(static::once())
            ->method('banAll');

        $clearer = new ReverseProxyCacheClearer($gateway);
        $clearer->clear('noop');
    }

    public function testClear67(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $gateway = $this->createMock(AbstractReverseProxyGateway::class);
        $gateway->expects(static::never())
            ->method('banAll');

        $clearer = new ReverseProxyCacheClearer($gateway);
        $clearer->clear('noop');
    }
}
