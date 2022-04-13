<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\ReverseProxy;

abstract class AbstractReverseProxyGateway
{
    abstract public function getDecorated(): AbstractReverseProxyGateway;

    /**
     * @deprecated tag:v6.5.0 - Parameter $response will be required
     */
    abstract public function tag(array $tags, string $url/*, Response $response */): void;

    abstract public function invalidate(array $tags): void;

    abstract public function ban(array $urls): void;

    /**
     * @deprecated tag:v6.5.0 - banAll method will be required
     */
    public function banAll(): void
    {
        $this->ban(['/']);
    }
}
