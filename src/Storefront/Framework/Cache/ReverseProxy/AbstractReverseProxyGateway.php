<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\ReverseProxy;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('storefront')]
abstract class AbstractReverseProxyGateway
{
    abstract public function getDecorated(): AbstractReverseProxyGateway;

    /**
     * @param string[] $tags
     *
     * @deprecated tag:v6.5.0 - Parameter $response will be required
     */
    abstract public function tag(array $tags, string $url/*, Response $response */): void;

    /**
     * @param string[] $tags
     */
    abstract public function invalidate(array $tags): void;

    /**
     * @param string[] $urls
     */
    abstract public function ban(array $urls): void;

    /**
     * @deprecated tag:v6.5.0 - banAll method will be abstract and required to be implemented by all implementations of this class
     */
    public function banAll(): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            'Method `banAll()` in "AbstractReverseProxyGateway" will be abstract in v6.5.0.0 and needs to be implemented by all implementations.'
        );

        $this->ban(['/']);
    }

    public function flush(): void
    {
    }
}
