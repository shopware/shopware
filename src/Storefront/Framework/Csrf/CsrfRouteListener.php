<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\Csrf\Exception\InvalidCsrfTokenException;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CsrfRouteListener implements EventSubscriberInterface
{
    /**
     * @var bool
     */
    protected $csrfEnabled;

    /**
     * @var string
     */
    protected $csrfMode;

    private CsrfTokenManagerInterface $csrfTokenManager;

    /**
     * Used to track if the csrf token has already been check for the request
     */
    private bool $csrfChecked = false;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(
        CsrfTokenManagerInterface $csrfTokenManager,
        bool $csrfEnabled,
        string $csrfMode,
        TranslatorInterface $translator
    ) {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->csrfEnabled = $csrfEnabled;
        $this->translator = $translator;
        $this->csrfMode = $csrfMode;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['csrfCheck', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_PRE],
            ],
        ];
    }

    public function csrfCheck(ControllerEvent $event): void
    {
        if (!$this->csrfEnabled || $this->csrfChecked === true) {
            return;
        }

        $request = $event->getRequest();

        if ($request->attributes->get(SalesChannelRequest::ATTRIBUTE_CSRF_PROTECTED, true) === false) {
            return;
        }

        if ($request->getMethod() !== Request::METHOD_POST) {
            return;
        }

        /** @var RouteScope|null $routeScope */
        $routeScope = $request->attributes->get('_routeScope');

        // Only check csrf token on storefront routes
        if ($routeScope === null || !$routeScope->hasScope(StorefrontRouteScope::ID)) {
            return;
        }

        $this->validateCsrfToken($request);
    }

    public function validateCsrfToken(Request $request): void
    {
        $this->csrfChecked = true;

        $submittedCSRFToken = (string) $request->request->get('_csrf_token');

        if ($this->csrfMode === CsrfModes::MODE_TWIG) {
            $intent = (string) $request->attributes->get('_route');
        } else {
            $intent = 'ajax';
        }
        $csrfCookies = (array) $request->cookies->get('csrf');
        if (
            (!isset($csrfCookies[$intent]) || $csrfCookies[$intent] !== $submittedCSRFToken)
            && !$this->csrfTokenManager->isTokenValid(new CsrfToken($intent, $submittedCSRFToken))
        ) {
            $session = $request->getSession();

            /* @see https://github.com/symfony/symfony/issues/41765 */
            if (method_exists($session, 'getFlashBag')) {
                if ($request->isXmlHttpRequest()) {
                    $session->getFlashBag()->add('danger', $this->translator->trans('error.message-403-ajax'));
                } else {
                    $session->getFlashBag()->add('danger', $this->translator->trans('error.message-403'));
                }
            }

            throw new InvalidCsrfTokenException();
        }
    }
}
