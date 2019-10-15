<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

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

    public function __construct(CsrfTokenManagerInterface $csrfTokenManager, bool $csrfEnabled)
    {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->csrfEnabled = $csrfEnabled;
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
        if (!$this->csrfEnabled) {
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
            throw new InvalidCsrfTokenException();
        }
    }
}
