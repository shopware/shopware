<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\ReverseProxy;

abstract class AbstractReverseProxyGateway
{
    abstract public function getDecorated(): AbstractReverseProxyGateway;

    abstract public function tag(array $tags, string $url): void;

    abstract public function invalidate(array $tags): void;

    abstract public function ban(array $urls): void;
}
