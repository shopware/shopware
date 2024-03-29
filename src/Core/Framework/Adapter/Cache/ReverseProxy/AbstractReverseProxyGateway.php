<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\ReverseProxy;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
abstract class AbstractReverseProxyGateway
{
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
