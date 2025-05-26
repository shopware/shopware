<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck\Util;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class SalesChannelDomainUtil
{
    public function __construct(
        protected Connection $connection,
        private RouterInterface $router,
        private RequestStack $requestStack,
    ) {
    }

    public function runAsSalesChannelRequest(callable $callback): Result
    {
        $mainRequest = $this->requestStack->getMainRequest();
        // the requests originate from CLI, there is no HTTP request.
        if ($mainRequest === null) {
            return $callback();
        }

        // If the request originates from a parent request, regardless of the main request
        // ensure it is treated as a sales channel request to access the storefront
        $hasSalesChannelRequest = $mainRequest->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST);
        $mainRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, true);

        try {
            return $callback();
        } finally {
            $mainRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, $hasSalesChannelRequest);
        }
    }

    public function runWhileTrustingAllHosts(callable $callback): Result
    {
        // Remove '{' from start and '}i' from end, applied by Request::setTrustedHosts.
        $trustedHosts = array_map(
            fn (string $pattern) => preg_replace('/^\{(.*)\}i$/', '$1', $pattern),
            Request::getTrustedHosts()
        );

        Request::setTrustedHosts([]);
        try {
            return $callback();
        } finally {
            Request::setTrustedHosts($trustedHosts);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function generateDomainUrl(string $url, string $routeName, array $parameters = []): string
    {
        return rtrim($url, '/') . $this->router->generate($routeName, $parameters);
    }

    public function createEmptyResult(string $name, string $message): Result
    {
        return new Result(
            $name,
            Status::SKIPPED,
            $message,
            true,
            []
        );
    }
}
