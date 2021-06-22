<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class StoreApiProxyController
{
    public const INHERIT_ATTRIBUTES = [
        SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE,
        SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID,
        SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID,
        SalesChannelRequest::ATTRIBUTE_DOMAIN_ID,
        SalesChannelRequest::ATTRIBUTE_THEME_ID,
        SalesChannelRequest::ATTRIBUTE_THEME_NAME,
        SalesChannelRequest::ATTRIBUTE_THEME_BASE_NAME,
        PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT,
        PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT,
    ];

    private KernelInterface $kernel;

    private RequestStack $requestStack;

    public function __construct(KernelInterface $kernel, RequestStack $requestStack)
    {
        $this->kernel = $kernel;
        $this->requestStack = $requestStack;
    }

    /**
     * @Since("6.3.2.0")
     * @Route("/_proxy/store-api", name="frontend.store-api.proxy", defaults={"XmlHttpRequest"=true})
     */
    public function proxy(Request $request, SalesChannelContext $context): Response
    {
        $storeApiRequest = $this->setupStoreApiRequest($request, $context);

        return $this->wrapInStoreApiRoute($storeApiRequest, function () use ($storeApiRequest): Response {
            return $this->kernel->handle($storeApiRequest, HttpKernelInterface::SUB_REQUEST);
        });
    }

    private function setupStoreApiRequest(Request $request, SalesChannelContext $context): Request
    {
        $path = $request->query->get('path');
        if ($path === null || !\is_string($path)) {
            throw new MissingRequestParameterException('path');
        }

        $url = parse_url($path);

        if ($url === false) {
            throw new InvalidRequestParameterException('path');
        }

        $query = null;
        if (isset($url['query'])) {
            parse_str($url['query'], $query);
        }

        $requestPath = $url['path'] ?? '';

        if (!\in_array(DefinitionService::STORE_API, explode('/', $requestPath), true)) {
            throw new InvalidRequestParameterException('The proxy controller can only be used for store-api routes');
        }

        $server = array_merge($request->server->all(), ['REQUEST_URI' => $requestPath]);
        $subRequest = $request->duplicate($query, null, [], null, null, $server);

        $subRequest->headers->set(PlatformRequest::HEADER_ACCESS_KEY, $context->getSalesChannel()->getAccessKey());
        $subRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $context->getToken());

        $subRequest->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $context->getSalesChannel()->getAccessKey());

        foreach (self::INHERIT_ATTRIBUTES as $inheritAttribute) {
            if ($request->attributes->has($inheritAttribute)) {
                $subRequest->attributes->set($inheritAttribute, $request->attributes->get($inheritAttribute));
            }
        }

        $subRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, true);

        if ($request->hasSession()) {
            $subRequest->setSession($request->getSession());
        }

        return $subRequest;
    }

    private function wrapInStoreApiRoute(Request $request, callable $call): Response
    {
        $requestStackBackup = $this->clearRequestStackWithBackup($this->requestStack);
        $this->requestStack->push($request);

        try {
            return $call();
        } finally {
            $this->restoreRequestStack($this->requestStack, $requestStackBackup);
        }
    }

    private function clearRequestStackWithBackup(RequestStack $requestStack): array
    {
        $requestStackBackup = [];

        while ($requestStack->getMainRequest()) {
            $requestStackBackup[] = $requestStack->pop();
        }

        return $requestStackBackup;
    }

    private function restoreRequestStack(RequestStack $requestStack, array $requestStackBackup): void
    {
        $this->clearRequestStackWithBackup($requestStack);

        foreach ($requestStackBackup as $backedUpRequest) {
            $requestStack->push($backedUpRequest);
        }
    }
}
