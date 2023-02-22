<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[Package('core')]
class KernelEventSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AppLocaleProvider $localeProvider,
        private readonly Translator $translator,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['resolveAuthorization', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE_PRE],
                ['resolveLanguage', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_PRE],
                ['resolveLocale', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_POST],
            ],
        ];
    }

    public function resolveAuthorization(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->attributes->get('admin_script')) {
            return;
        }
        if ($request->headers->has('Authorization')) {
            return;
        }
        if (!$request->cookies->has('admin_auth')) {
            $request->attributes->set('auth_required', false);
            $redirectUrl = $request->getRequestUri();
            $redirectUrl = $this->urlGenerator->generate('administration.index', ['redirectUrl' => $redirectUrl]);
            $event->setController(function () use ($redirectUrl): RedirectResponse {
                return new RedirectResponse($redirectUrl);
            });

            return;
        }
        $request->headers->set('Authorization', 'Bearer ' . $request->cookies->get('admin_auth'));
    }

    public function resolveLanguage(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->attributes->get('admin_script')) {
            return;
        }
        if ($request->headers->has(PlatformRequest::HEADER_LANGUAGE_ID)) {
            return;
        }
        $language = $request->get(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE);
        if ($language === null) {
            return;
        }
        $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, $language);
    }

    public function resolveLocale(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->attributes->get('admin_script')) {
            return;
        }
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            return;
        }
        $locale = $this->localeProvider->getLocaleFromContext($context);
        $this->translator->setLocale($locale);
    }
}
