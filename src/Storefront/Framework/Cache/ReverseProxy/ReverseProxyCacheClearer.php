<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\ReverseProxy;

use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

class ReverseProxyCacheClearer implements CacheClearerInterface
{
    protected AbstractReverseProxyGateway $gateway;

    protected array $banUrls;

    public function __construct(AbstractReverseProxyGateway $gateway, array $banUrls)
    {
        $this->gateway = $gateway;
        $this->banUrls = $banUrls;
    }

    public function clear(string $cacheDir): void
    {
        $this->gateway->ban($this->banUrls);
    }
}
