<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Framework\Routing\Exception\TouchpointNotFoundException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class TouchpointRequestContextResolver implements RequestContextResolverInterface
{
    /**
     * @var RequestContextResolverInterface
     */
    private $decorated;

    /**
     * @var CheckoutContextService
     */
    private $contextService;

    public function __construct(
        RequestContextResolverInterface $decorated,
        CheckoutContextService $contextService
    ) {
        $this->decorated = $decorated;
        $this->contextService = $contextService;
    }

    public function resolve(SymfonyRequest $master, SymfonyRequest $request): void
    {
        if (!$master->attributes->has(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID)) {
            $this->decorated->resolve($master, $request);

            return;
        }

        if ($master->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID) === 'administration') {
            $this->decorated->resolve($master, $request);

            return;
        }

        if (!$master->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            $master->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, Uuid::uuid4()->getHex());
        }

        if (!$master->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            try {
                $this->decorated->resolve($master, $request);
            } catch (\Exception $e) {
                throw new \RuntimeException('No context token detected', 400, $e);
            }

            return;
        }

        $this->validateRequest($master);

        $contextToken = $master->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $tenantId = $master->headers->get(PlatformRequest::HEADER_TENANT_ID);
        $touchpointId = $master->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID);

        //sub requests can use the context of the master request
        if ($master->attributes->has(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT)) {
            $context = $master->attributes->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);
        } else {
            $context = $this->contextService->get($tenantId, $touchpointId, $contextToken);
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT, $context);
    }

    private function validateRequest(Request $request): void
    {
        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID)) {
            throw new TouchpointNotFoundException();
        }
    }
}
