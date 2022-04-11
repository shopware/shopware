<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\ReverseProxy;

use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

class ReverseProxyCacheClearer implements CacheClearerInterface
{
    protected AbstractReverseProxyGateway $gateway;

    public function __construct(AbstractReverseProxyGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function clear(string $cacheDir): void
    {
        $this->gateway->banAll();
    }
}
