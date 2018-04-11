<?php

namespace Shopware\Framework\Routing;

use Shopware\Framework\Struct\Uuid;
use Shopware\StorefrontApi\Context\StorefrontContextService;
use Shopware\StorefrontApi\Firewall\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ApplicationRequestContextResolver implements RequestContextResolverInterface
{
    public const STOREFRONT_CONTEXT_REQUEST_ATTRIBUTE = 'x-sw-storefront-context';

    public const APPLICATION_HEADER = 'x-sw-application-token';

    public const CONTEXT_TOKEN_HEADER = 'x-sw-context-token';

    /**
     * @var RequestContextResolverInterface
     */
    private $decorated;

    /**
     * @var StorefrontContextService
     */
    private $contextService;
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    public function __construct(
        RequestContextResolverInterface $decorated,
        StorefrontContextService $contextService,
        TokenStorageInterface $tokenStorage
    ) {
        $this->decorated = $decorated;
        $this->contextService = $contextService;
        $this->tokenStorage = $tokenStorage;
    }

    public function resolve(Request $master, Request $request): void
    {
        if (!$this->tokenStorage->getToken()) {
            $this->decorated->resolve($master, $request);

            return;
        }
        /** @var Application $application */
        $application = $this->tokenStorage->getToken()->getUser();

        if (!$application instanceof Application) {
            $this->decorated->resolve($master, $request);

            return;
        }

        if (!$master->headers->has(self::CONTEXT_TOKEN_HEADER)) {
            $master->headers->set(self::CONTEXT_TOKEN_HEADER, Uuid::uuid4()->getHex());
        }

        if (!$master->headers->get(self::CONTEXT_TOKEN_HEADER)) {
            try {
                $this->decorated->resolve($master, $request);
            } catch (\Exception $e) {
                throw new \RuntimeException('No context token detected', 400, $e);
            }

            return;
        }

        $contextToken = $master->headers->get(self::CONTEXT_TOKEN_HEADER);
        $applicationId = $application->getApplicationId();

        //sub requests can use the context of the master request
        if ($master->attributes->has(self::STOREFRONT_CONTEXT_REQUEST_ATTRIBUTE)) {
            $context = $master->attributes->get(self::STOREFRONT_CONTEXT_REQUEST_ATTRIBUTE);
        } else {
            $context = $this->contextService->getStorefrontContext($applicationId, $contextToken);
        }

        $request->attributes->set(self::CONTEXT_REQUEST_ATTRIBUTE, $context->getApplicationContext());
        $request->attributes->set(self::STOREFRONT_CONTEXT_REQUEST_ATTRIBUTE, $context);
    }
}