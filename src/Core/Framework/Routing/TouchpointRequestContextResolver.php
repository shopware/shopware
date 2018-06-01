<?php declare(strict_types=1);

namespace Shopware\Framework\Routing;

use Shopware\Checkout\Customer\Util\CustomerContextService;
use Shopware\Framework\Routing\Firewall\Touchpoint;
use Shopware\Framework\Struct\Uuid;
use Shopware\PlatformRequest;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class TouchpointRequestContextResolver implements RequestContextResolverInterface
{
    /**
     * @var RequestContextResolverInterface
     */
    private $decorated;

    /**
     * @var CustomerContextService
     */
    private $contextService;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    public function __construct(
        RequestContextResolverInterface $decorated,
        CustomerContextService $contextService,
        TokenStorageInterface $tokenStorage
    ) {
        $this->decorated = $decorated;
        $this->contextService = $contextService;
        $this->tokenStorage = $tokenStorage;
    }

    public function resolve(SymfonyRequest $master, SymfonyRequest $request): void
    {
        if (!$this->tokenStorage->getToken()) {
            $this->decorated->resolve($master, $request);

            return;
        }
        /** @var \Shopware\Framework\Routing\Firewall\Touchpoint $touchpoint */
        $touchpoint = $this->tokenStorage->getToken()->getUser();

        if (!$touchpoint instanceof Touchpoint) {
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

        $contextToken = $master->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $touchpointId = $touchpoint->getTouchpointId();
        $tenantId = $master->headers->get(PlatformRequest::HEADER_TENANT_ID);

        //sub requests can use the context of the master request
        if ($master->attributes->has(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT)) {
            $context = $master->attributes->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);
        } else {
            $context = $this->contextService->get($tenantId, $touchpointId, $contextToken);
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT, $context);
    }
}
