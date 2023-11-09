<?php declare(strict_types=1);

// @deprecated tag:v6.6.0 - Remove double namespace and class exists check

namespace Shopware\Storefront\Framework\Cache\ReverseProxy {
    use Shopware\Core\Framework\Log\Package;
    use Symfony\Component\HttpFoundation\Response;

    if (!class_exists(AbstractReverseProxyGateway::class)) {
        #[Package('core')]
        abstract class AbstractReverseProxyGateway
        {
            /**
             * @deprecated tag:v6.6.0 - will be removed
             */
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
    }
}

namespace Shopware\Core\Framework\Adapter\Cache\ReverseProxy {
    use Shopware\Core\Framework\Log\Package;
    use Symfony\Component\HttpFoundation\Response;

    #[Package('core')]
    abstract class AbstractReverseProxyGateway extends \Shopware\Storefront\Framework\Cache\ReverseProxy\AbstractReverseProxyGateway
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
}
