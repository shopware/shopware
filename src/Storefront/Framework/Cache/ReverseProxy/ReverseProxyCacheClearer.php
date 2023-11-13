<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\ReverseProxy;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

#[Package('storefront')]
class ReverseProxyCacheClearer implements CacheClearerInterface
{
    /**
     * @internal
     */
    public function __construct(protected AbstractReverseProxyGateway $gateway)
    {
    }

    public function clear(string $cacheDir): void
    {
        $this->gateway->banAll();
    }
}
