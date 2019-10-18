<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Translation\DataCollectorTranslator;

class CsrfRouteListener implements EventSubscriberInterface
{
    /**
     * @var CsrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /**
     * @var bool
     */
    private $csrfEnabled;

    /**
     * Used to track if the csrf token has already been check for the request
     *
     * @var bool
     */
    private $csrfChecked = false;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var DataCollectorTranslator
     */
    private $translator;

    public function __construct(
        CsrfTokenManagerInterface $csrfTokenManager,
        bool $csrfEnabled,
        Session $session,
        DataCollectorTranslator $translator
    ) {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->csrfEnabled = $csrfEnabled;
        $this->session = $session;
        $this->translator = $translator;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['csrfCheck', -15],
            ],
        ];
    }

    public function csrfCheck(ControllerEvent $event): void
    {
        if (!$this->csrfEnabled || $this->csrfChecked === true) {
            return;
        }

        $request = $event->getRequest();

        if ($request->attributes->get('csrf_protected', true) === false) {
            return;
        }

        if ($request->getMethod() !== Request::METHOD_POST) {
            return;
        }

        /** @var RouteScope|null $routeScope */
        $routeScope = $request->attributes->get('_routeScope');

        // Only check csrf token on storefront routes
        if ($routeScope && !in_array('storefront', $routeScope->getScopes(), true)) {
            return;
        }

        $this->validateCsrfToken($request);
    }

    public function validateCsrfToken(Request $request): void
    {
        $submittedCSRFToken = $request->request->get('_csrf_token');
        $intent = $request->attributes->get('_route');

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($intent, $submittedCSRFToken))) {
            if ($request->isXmlHttpRequest()) {
                $this->session->getFlashBag()->add('danger', $this->translator->trans('error.message-403-ajax'));
            } else {
                $this->session->getFlashBag()->add('danger', $this->translator->trans('error.message-403'));
            }

            throw new InvalidCsrfTokenException('Invalid CSRF token.', 403);
        }

        $this->csrfChecked = true;
    }
}
