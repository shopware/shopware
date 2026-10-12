<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\ReverseProxy;

use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;

/**
 * Selects the http cache store and reverse proxy gateway when the services are instantiated,
 * so the reverse proxy configuration can be changed at runtime, e.g. via environment variables.
 *
 * @internal
 */
#[Package('framework')]
final class ReverseProxyServiceFactory
{
    public const DEFAULT_STORE = 'default';
    public const REVERSE_PROXY_STORE = 'reverse_proxy';

    public const VARNISH_GATEWAY = 'varnish';
    public const FASTLY_GATEWAY = 'fastly';

    public static function createStore(bool $reverseProxyEnabled, ContainerInterface $stores): StoreInterface
    {
        return $stores->get($reverseProxyEnabled ? self::REVERSE_PROXY_STORE : self::DEFAULT_STORE);
    }

    public static function createGateway(bool $fastlyEnabled, ContainerInterface $gateways): AbstractReverseProxyGateway
    {
        return $gateways->get($fastlyEnabled ? self::FASTLY_GATEWAY : self::VARNISH_GATEWAY);
    }
}
