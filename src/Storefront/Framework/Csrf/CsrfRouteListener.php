<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\Csrf\Exception\InvalidCsrfTokenException;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - will be removed
 */
class CsrfRouteListener implements EventSubscriberInterface, ResetInterface
{
    /**
     * @deprecated tag:v6.5.0 - reason:visibility-change - Will become private and natively typed to bool
     *
     * @var bool
     */
    protected $csrfEnabled;

    /**
     * @deprecated tag:v6.5.0 - reason:visibility-change - Will become private and natively typed to string
     *
     * @var string
     */
    protected $csrfMode;

    private CsrfTokenManagerInterface $csrfTokenManager;

    /**
     * Used to track if the csrf token has already been check for the request
     */
    private bool $csrfChecked = false;

    private TranslatorInterface $translator;

    /**
     * @internal
     */
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
        if (Feature::isActive('v6.5.0.0')) {
            return [];
        }

        return [
            KernelEvents::CONTROLLER => [
                ['csrfCheck', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_PRE],
            ],
        ];
    }

    public function csrfCheck(ControllerEvent $event): void
    {
        if (Feature::isActive('v6.5.0.0')) {
            return;
        }

        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

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

        /** @var RouteScope|list<string> $scopes */
        $scopes = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        if ($scopes instanceof RouteScope) {
            $scopes = $scopes->getScopes();
        }

        // Only check csrf token on storefront routes
        if (!\in_array(StorefrontRouteScope::ID, $scopes, true)) {
            return;
        }

        $this->validateCsrfToken($request);
    }

    /**
     * @deprecated tag:v6.5.0 - reason:visibility-change - method will become private in v6.5.0
     */
    public function validateCsrfToken(Request $request): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        $this->csrfChecked = true;

        $submittedCSRFToken = (string) $request->request->get('_csrf_token');

        if ($this->csrfMode === CsrfModes::MODE_TWIG) {
            $intent = (string) $request->attributes->get('_route');
        } else {
            $intent = 'ajax';
        }
        $csrfCookies = $request->cookies->all('csrf');
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

    public function reset(): void
    {
        $this->csrfChecked = false;
    }
}
