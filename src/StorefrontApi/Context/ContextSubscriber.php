<?php

namespace Shopware\StorefrontApi\Context;

use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ContextSubscriber implements EventSubscriberInterface
{
    const SHOP_CONTEXT_PROPERTY = 'shop_context';

    /**
     * @var StorefrontContextService
     */
    private $contextService;

    public function __construct(StorefrontContextService $contextService)
    {
        $this->contextService = $contextService;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                ['setContextToken', 15],
                ['loadContext', 5],
            ]
        ];
    }

    public function loadContext(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        if ($request->attributes->has(self::SHOP_CONTEXT_PROPERTY)) {
            return;
        }

        $token = $request->attributes->get(StorefrontContextValueResolver::CONTEXT_TOKEN_KEY);
        $applicationId = $request->attributes->get(StorefrontContextValueResolver::APPLICATION_ID);

        if (!$token || !$applicationId) {
            return;
        }

        $context = $this->contextService->getStorefrontContext($applicationId, $token);

        $request->attributes->set(self::SHOP_CONTEXT_PROPERTY, $context);
    }

    public function setContextToken(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $token = $this->getToken($request);

        $request->attributes->set(StorefrontContextValueResolver::CONTEXT_TOKEN_KEY, $token);
    }

    private function getToken(Request $request)
    {
        if ($request->headers->has(StorefrontContextValueResolver::CONTEXT_TOKEN_KEY)) {
            return $request->headers->get(StorefrontContextValueResolver::CONTEXT_TOKEN_KEY);
        }
        return Uuid::uuid4()->toString();
    }
}