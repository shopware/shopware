<?php declare(strict_types=1);

namespace Shopware\Storefront\Routing;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\StorefrontRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class StorefrontSubscriber implements EventSubscriberInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(RequestStack $requestStack, RouterInterface $router)
    {
        $this->requestStack = $requestStack;
        $this->router = $router;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                ['startSession', 40],
//                ['seoRedirect', 2]
            ],
            KernelEvents::EXCEPTION => [
////                ['redirectIfNotFound'],
                ['showHtmlExceptionResponse', -100],
                ['customerNotLoggedInHandler'],
            ],
        ];
    }

    public function startSession(GetResponseEvent $event): void
    {
        $master = $this->requestStack->getMasterRequest();
        if (!$master->attributes->get(StorefrontRequest::ATTRIBUTE_IS_STOREFRONT_REQUEST)) {
            return;
        }

        $session = $master->getSession();
        if (!$session) {
            return;
        }

        $applicationId = $master->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID);

        if (!$session->isStarted()) {
            $session->setName('session-' . $applicationId);
            $session->start();
            $session->set('sessionId', $session->getId());
        }

        if (!$session->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            $token = Uuid::uuid4()->getHex();
            $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
        }

        $master->headers->set(
            PlatformRequest::HEADER_CONTEXT_TOKEN,
            $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN)
        );
    }

    public function showHtmlExceptionResponse(GetResponseForExceptionEvent $event)
    {
        if ($event->getRequest()->attributes->has(StorefrontRequest::ATTRIBUTE_IS_STOREFRONT_REQUEST)) {
            $event->stopPropagation();
        }
    }

    public function customerNotLoggedInHandler(GetResponseForExceptionEvent $event)
    {
        if (!$event->getRequest()->attributes->has(StorefrontRequest::ATTRIBUTE_IS_STOREFRONT_REQUEST)) {
            return;
        }

        if (!$event->getException() instanceof CustomerNotLoggedInException) {
            return;
        }

        $parameters = [
            'redirectTo' => urlencode($event->getRequest()->getRequestUri()),
        ];

        $redirectResponse = new RedirectResponse($this->router->generate('account_login', $parameters));

        $event->setResponse($redirectResponse);
    }

//
//    public function redirectIfNotFound(GetResponseForExceptionEvent $event)
//    {
//        if (false === $event->getRequest()->attributes->has(self::STOREFRONT_REQUEST_ATTRIBUTE)) {
//            return;
//        }
//
//        if ($event->getException() instanceof NotFoundHttpException === false) {
//            return;
//        }
//
//        $redirectResponse = new RedirectResponse($this->router->generate('homepage'));
//
//        $event->setResponse($redirectResponse);
//    }
//
//    public function seoRedirect(GetResponseEvent $event)
//    {
//        if (false === $event->getRequest()->attributes->has(self::STOREFRONT_SEO_REDIRECT)) {
//            return;
//        }
//
//        $event->stopPropagation();
//        $event->setResponse(new RedirectResponse($event->getRequest()->attributes->get(self::STOREFRONT_SEO_REDIRECT)));
//    }
}
