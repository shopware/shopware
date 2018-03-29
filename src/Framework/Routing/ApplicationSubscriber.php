<?php

namespace Shopware\Framework\Routing;

use Shopware\Framework\Application\ApplicationInfo;
use Shopware\Framework\Application\ApplicationResolverInterface;
use Shopware\Framework\Struct\Uuid;
use Shopware\StorefrontApi\Context\StorefrontContextServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApplicationSubscriber implements EventSubscriberInterface
{
    public const SHOP_CONTEXT_PROPERTY = '_platform_context';
    public const APPLICATION_INFO = '_platform_app';
    /**
     * @var ApplicationResolverInterface
     */
    private $applicationResolver;

    /**
     * @var StorefrontContextServiceInterface
     */
    private $contextService;

    /**
     * @var string[]
     */
    private $whitelist = [
        '/_wdt/',
        '/_profiler/',
        '/_error/'
    ];

    public function __construct(ApplicationResolverInterface $applicationResolver, StorefrontContextServiceInterface $contextService)
    {
        $this->applicationResolver = $applicationResolver;
        $this->contextService = $contextService;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                ['bootstrap', 39]
            ]
        ];
    }


    public function bootstrap(GetResponseEvent $event): void
    {
        /*
            # plain php

            - resolve base url
                - example /de/account => /account


            ## kernel boot

            - security auth. (required, base url resolved)
                - resolves user

            - firewall

            - context resolving
                - prio:
                    - user token?       (admin - rest)
                        - runtime + user defaults
                        - LOADS "SHOP-CONTEXT" (minimal)

                    - app token         (storefront api)
                        - runtime + app defaults
                        + LOADS STOREFRONT-CONTEXT  (full)

            - routing: (requires base url resolved + context parameters)

        */
        if (!$event->isMasterRequest() || !$this->isApplicationRequired($event->getRequest())) {
            return;
        }

        $appInfo = new ApplicationInfo();
        $event->getRequest()->attributes->set(self::APPLICATION_INFO, $appInfo);

        $this->applicationResolver->resolveApplication($event->getRequest(), $appInfo);
        $this->applicationResolver->resolveContextToken($event->getRequest(), $appInfo);

        if (!$appInfo->getContextId()) {
            $appInfo->setContextId(Uuid::uuid4()->getHex());
        }

        $appInfo->setStorefrontContext(
            $this->contextService->getStorefrontContext($appInfo->getApplicationId(), $appInfo->getContextId())
        );

        return;
//                $this->findApplication($event);
                // parent::call
                    // platform => get header value
                    // else throw exception
                // catch exception
                    // find application by app config domains

                $this->setContextToken($event);
                    // twig:
                        // is active application === storefront-application?        brauch information aus decorated storefront appId resolver
                            // "start session" (if not started)
                            // get session context token / or create
                        // else
                            // platform => header value?
                            // else create

                $this->loadContext($event);

//{
//    applicationId: 'fffff',
//    contextToken: 'fffff',
//    baseUrl: '/de',
//    requestType: 'api OR storefront-api OR storefront'
//
//}




        $this->route();
            // is storefront request?
                // strip base url       (app config aus appId resolver)
                // seo routing          (context)
            // else
                // platform simple matching

    }

//    public function findApplication(GetResponseEvent $event): void
//    {
//
//
//        if (false === $this->isApplicationRequired($event->getRequest())) {
//            return;
//        }
//
//        $request = $this->getRequest($event);
//
//        if ($request->attributes->has(ApplicationAuthenticator::APPLICATION_ACCESS_KEY)) {
//            $event->getRequest()->attributes->set(
//                ApplicationAuthenticator::APPLICATION_ACCESS_KEY,
//                $request->attributes->get(ApplicationAuthenticator::APPLICATION_ACCESS_KEY)
//            );
//
//            $event->getRequest()->attributes->set(
//                ApplicationAuthenticator::APPLICATION_CONFIGURATION,
//                $request->attributes->get(ApplicationAuthenticator::APPLICATION_CONFIGURATION)
//            );
//
//            return;
//        }
//
//        $applicationId = $this->applicationResolver->resolve($event->getRequest());
//        if (!$applicationId) {
//            throw new NotFoundHttpException('Request could not be matched to an application.');
//        }
//
//        $event->getRequest()->attributes->set(ApplicationAuthenticator::APPLICATION_ACCESS_KEY, $applicationId);
//    }

//    private function getRequest(GetResponseEvent $event): Request
//    {
//        if ($this->requestStack->getMasterRequest() !== $event->getRequest()) {
//            return $this->requestStack->getMasterRequest();
//        }
//
//        return $event->getRequest();
//    }

    public function isApplicationRequired(Request $request): bool
    {
        foreach ($this->whitelist as $prefix) {
            if (strpos($request->getPathInfo(), $prefix) === 0) {
                return false;
            }
        }

        return true;
    }
}