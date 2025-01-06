<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\ReverseProxy;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

/**
 * @deprecated tag:v6.7.0 reason:remove-subscriber - Will be removed with no replacement
 *
 * @internal
 */
#[Package('core')]
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
        Feature::ifNotActive('v6.7.0.0', fn () => $this->gateway->banAll());
    }
}
