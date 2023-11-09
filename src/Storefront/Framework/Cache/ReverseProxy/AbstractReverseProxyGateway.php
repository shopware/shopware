<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\ReverseProxy;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - reason:class-hierarchy-change - Use \Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway instead
 */
#[Package('core')]
abstract class AbstractReverseProxyGateway
{
    abstract public function getDecorated(): AbstractReverseProxyGateway;

    /**
     * @param string[] $tags
     */
    abstract public function tag(array $tags, string $url, Response $response): void;

    /**
     * @param string[] $tags
     */
    abstract public function invalidate(array $tags): void;

    /**
     * @param string[] $urls
     */
    abstract public function ban(array $urls): void;

    abstract public function banAll(): void;

    public function flush(): void
    {
    }
}
