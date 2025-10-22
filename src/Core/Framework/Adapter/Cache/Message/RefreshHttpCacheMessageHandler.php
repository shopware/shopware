<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Message;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[AsMessageHandler]
#[Package('framework')]
final readonly class RefreshHttpCacheMessageHandler
{
    public function __construct(
        private HttpKernelInterface $kernel,
        private StoreInterface $store,
        private CacheInterface $cache,
    ) {
    }

    public function __invoke(RefreshHttpCacheMessage $msg): void
    {
        /** @var int-mask-of<Request::HEADER_*> $beforeTrustedHeaderSet */
        $beforeTrustedHeaderSet = Request::getTrustedHeaderSet();
        $beforeTrustedIps = Request::getTrustedProxies();

        try {
            // @phpstan-ignore argument.type (TrustedProxies are not correctly typed in Request getters)
            Request::setTrustedProxies($msg->trustedIps, $msg->trustedHeaderSet);

            $request = new Request(
                $msg->query,
                [],
                $msg->attributes,
                $msg->cookies,
                [],
                $msg->server
            );

            /**
             * We create a mock session to prevent session-related errors during request handling.
             * Since this is for cache regeneration only, the mock session has no impact on the
             * response content - we're just generating a cacheable response that doesn't include
             * any session-specific data.
             */
            $request->setSession(new Session(new MockArraySessionStorage()));

            $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
            $this->store->write($request, $response);

            $this->cache->delete($msg->lockKey);
        } finally {
            Request::setTrustedProxies($beforeTrustedIps, $beforeTrustedHeaderSet);
        }
    }
}
