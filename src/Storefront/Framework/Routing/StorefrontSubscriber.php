<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Controller\ErrorController;
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

    /**
     * @var ErrorController
     */
    private $errorController;

    public function __construct(RequestStack $requestStack, RouterInterface $router, ErrorController $errorController)
    {
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->errorController = $errorController;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                ['startSession', 40],
            ],
            KernelEvents::EXCEPTION => [
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

    public function showHtmlExceptionResponse(GetResponseForExceptionEvent $event): void
    {
        if ($event->getRequest()->attributes->has(StorefrontRequest::ATTRIBUTE_IS_STOREFRONT_REQUEST)) {
            $event->stopPropagation();
            $content = $this->errorController->error($event->getException(), $this->requestStack->getMasterRequest());
            $event->setResponse($content);
        }
    }

    public function customerNotLoggedInHandler(GetResponseForExceptionEvent $event): void
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

        $redirectResponse = new RedirectResponse($this->router->generate('frontend.account.login.page', $parameters));

        $event->setResponse($redirectResponse);
    }
}
