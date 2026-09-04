<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class SalesChannelRequestContextResolver implements RequestContextResolverInterface
{
    use RouteScopeCheckTrait;

    /**
     * @internal
     */
    public function __construct(
        private readonly RequestContextResolverInterface $decorated,
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RouteScopeRegistry $routeScopeRegistry,
        private readonly SessionContextTokenAccessor $sessionContextToken
    ) {
    }

    public function resolve(Request $request): void
    {
        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID)) {
            $this->decorated->resolve($request);

            return;
        }

        if (!$this->isRequestScoped($request, SalesChannelContextRouteScopeDependant::class)) {
            return;
        }

        if (!$request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            $this->resolveContextTokenFromSession($request);
        }

        if (!$request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            if ($this->contextTokenRequired($request)) {
                throw RoutingException::missingRequestParameter(PlatformRequest::HEADER_CONTEXT_TOKEN);
            }

            $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, Random::getAlphanumericString(32));
        }

        // $skipIfUninitialized = true is intentional: storefront sessions are started before context resolution,
        // while Store API requests only have a lazy session factory and must remain stateless.
        $session = $request->hasSession(true) ? $request->getSession() : null;
        $session = $session?->isStarted() ? $session : null;

        // Retrieve context for current request
        $usedContextToken = (string) $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        // Remember what this request came in with, so a later rotation stays recognizable as one.
        $request->attributes->set(SessionContextTokenAccessor::ATTRIBUTE_RESOLVED_TOKEN, $usedContextToken);

        $languageId = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID, '');
        $currencyId = $request->headers->get(PlatformRequest::HEADER_CURRENCY_ID, '');

        $contextServiceParameters = new SalesChannelContextServiceParameters(
            (string) $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID),
            $usedContextToken,
            $languageId !== '' ? $languageId : null,
            $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID),
            $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID),
            $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT),
            null,
            $session?->get(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID),
            // overwrite currency id based on request header if it is set
            $currencyId !== '' ? $currencyId : null
        );
        $context = $this->contextService->get($contextServiceParameters);

        // Validate if a customer login is required for the current request
        $this->validateLogin($request, $context);

        $this->eventDispatcher->dispatch(
            new SalesChannelContextResolvedEvent($context, $usedContextToken)
        );
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }

    /**
     * A caller that declared `sw-context-source: session` and sent no `sw-context-token` header
     * continues the shopper's storefront context.
     *
     * The declaration is a contract: when the session cannot be used, the request fails instead of
     * silently falling through to a fresh throwaway token - to a session-based client a fresh token
     * per request would surface as an inexplicably empty cart, while the error names the condition
     * that was not met.
     *
     * On success the token is put on the request headers, so every downstream consumer - context
     * service, cart, rotation - sees exactly what it would have seen for a client sent token, and
     * the request is marked so the response can be kept out of shared caches.
     */
    private function resolveContextTokenFromSession(Request $request): void
    {
        if (!$this->sessionContextToken->isRequested($request)) {
            return;
        }

        $reason = $this->sessionContextToken->ineligibilityReason($request);

        if ($reason !== null) {
            throw RoutingException::sessionContextNotResolvable($reason);
        }

        $salesChannelId = (string) $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);

        $token = $this->sessionContextToken->read($request, $salesChannelId);

        if ($token === null) {
            throw RoutingException::sessionContextNotResolvable(
                'the session cookie does not resume a storefront session holding a context token'
            );
        }

        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
        $request->attributes->set(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION, true);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_NO_STORE, true);
    }

    private function contextTokenRequired(Request $request): bool
    {
        return (bool) $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED, false);
    }

    private function validateLogin(Request $request, SalesChannelContext $context): void
    {
        if (!$request->attributes->get(PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED)) {
            return;
        }

        if ($context->getCustomer() === null) {
            throw RoutingException::customerNotLoggedIn();
        }

        if ($request->attributes->get(PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST, false) === false && $context->getCustomer()->getGuest()) {
            throw RoutingException::customerNotLoggedIn();
        }
    }
}
