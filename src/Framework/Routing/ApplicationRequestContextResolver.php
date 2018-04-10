<?php

namespace Shopware\Framework\Routing;

use Shopware\StorefrontApi\Context\StorefrontContextService;
use Symfony\Component\HttpFoundation\Request;
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
        $appToken = $master->headers->get(self::APPLICATION_HEADER);
        
        if (!$appToken) {
            try {
                $this->decorated->resolve($master, $request);
            } catch (\Exception $e) {
                throw new \RuntimeException('No application detected', 400, $e);
            }

            return;
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
        $applicationId = $appToken;

        //todo@jb implement token storage for application authentification and get application id of app token
//        $auth = $this->tokenStorage->getToken();
//        if ($auth->getUser()) {
//            $applicationId = $auth->getUser()->getApplicationId();
//            $contextToken = $auth->getUser()->getContextToken();
//        }

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