<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Context;

use Shopware\StorefrontApi\Firewall\ApplicationAuthenticator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ContextSubscriber implements EventSubscriberInterface
{
    public const SHOP_CONTEXT_PROPERTY = 'shop_context';

    /**
     * @var StorefrontContextService
     */
    private $contextService;

    /**
     * @var ContextTokenResolverInterface
     */
    private $tokenResolver;

    public function __construct(StorefrontContextService $contextService, ContextTokenResolverInterface $tokenResolver)
    {
        $this->contextService = $contextService;
        $this->tokenResolver = $tokenResolver;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                ['setContextToken', 10],
                ['loadContext', 5],
            ],
        ];
    }

    public function loadContext(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        if ($request->attributes->has(self::SHOP_CONTEXT_PROPERTY)) {
            return;
        }

        $token = $request->attributes->get(ApplicationAuthenticator::CONTEXT_TOKEN_KEY);
        $applicationId = $request->attributes->get(ApplicationAuthenticator::APPLICATION_ID);

        if (!$token || !$applicationId) {
            return;
        }

        $context = $this->contextService->getStorefrontContext($applicationId, $token);

        $request->attributes->set(self::SHOP_CONTEXT_PROPERTY, $context);
    }

    public function setContextToken(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $token = $this->tokenResolver->resolve($request);

        $request->attributes->set(ApplicationAuthenticator::CONTEXT_TOKEN_KEY, $token);
    }
}
