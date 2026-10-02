<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck\Util;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('discovery')]
readonly class SalesChannelDomainUtil
{
    private const MAX_REDIRECTS = 5;

    public function __construct(
        private RouterInterface $router,
        private RequestStack $requestStack,
        private KernelInterface $kernel,
        private LoggerInterface $logger,
        private ClockInterface $clock,
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
            static fn (string $pattern) => preg_replace('/^\{(.*)\}i$/', '$1', $pattern),
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

    /**
     * @description Records an exception that prevented a storefront page from being probed as the failed result
     * of that page, so the check can go on with the other sales channels and still report what went wrong.
     */
    public function createExceptionResult(string $url, \Exception $e, float $responseTime = 0.0, ?Request $request = null): StorefrontHealthCheckResult
    {
        $this->logger->error(\sprintf('Error during systemcheck: "%s"', $e->getMessage()), ['exception' => $e, 'request' => $request]);

        return StorefrontHealthCheckResult::create($url, Response::HTTP_BAD_REQUEST, $responseTime, $e->getMessage());
    }

    /**
     * @description Handles a request and follows redirects (e.g. for SEO) if necessary to return the final response.
     */
    public function handleRequest(Request $request): StorefrontHealthCheckResult
    {
        $currentRequest = $request;
        $responseTime = 0.0;
        $redirectCount = 0;

        $response = null;

        while ($redirectCount <= self::MAX_REDIRECTS) {
            $requestStart = (float) $this->clock->now()->format(Defaults::MICROTIME_FORMAT);
            try {
                // don't let the kernel catch errors, so we can handle them ourselves
                $response = $this->kernel->handle($currentRequest, catch: false);
            } catch (\Exception $e) {
                $responseTime += (float) $this->clock->now()->format(Defaults::MICROTIME_FORMAT) - $requestStart;

                return $this->createExceptionResult($currentRequest->getUri(), $e, $responseTime, $currentRequest);
            }
            $responseTime += (float) $this->clock->now()->format(Defaults::MICROTIME_FORMAT) - $requestStart;

            if (!$response instanceof RedirectResponse) {
                break;
            }

            ++$redirectCount;

            $currentRequest = Request::create($response->getTargetUrl());
        }

        if ($redirectCount > self::MAX_REDIRECTS) {
            return StorefrontHealthCheckResult::create($currentRequest->getUri(), Response::HTTP_LOOP_DETECTED, $responseTime);
        }

        return StorefrontHealthCheckResult::create($currentRequest->getUri(), $response->getStatusCode(), $responseTime);
    }
}
