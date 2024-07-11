<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Checkout\Cart\CartException;
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
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

#[Package('core')]
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
        private readonly RouteScopeRegistry $routeScopeRegistry
    ) {
    }

    public function resolve(SymfonyRequest $request): void
    {
        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID)) {
            $this->decorated->resolve($request);

            return;
        }

        if (!$this->isRequestScoped($request, SalesChannelContextRouteScopeDependant::class)) {
            return;
        }

        if (!$request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            if ($this->contextTokenRequired($request)) {
                throw RoutingException::missingRequestParameter(PlatformRequest::HEADER_CONTEXT_TOKEN);
            }

            $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, Random::getAlphanumericString(32));
        }

        $session = $request->hasSession() ? $request->getSession() : null;

        // Retrieve context for current request
        $usedContextToken = (string) $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $contextServiceParameters = new SalesChannelContextServiceParameters(
            (string) $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID),
            $usedContextToken,
            $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID),
            $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID),
            $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID),
            null,
            null,
            $session?->get(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID)
        );
        $context = $this->contextService->get($contextServiceParameters);

        // Validate if a customer login is required for the current request
        $this->validateLogin($request, $context);

        // Update attributes and headers of the current request
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $context->getToken());

        $this->eventDispatcher->dispatch(
            new SalesChannelContextResolvedEvent($context, $usedContextToken)
        );
    }

    public function handleSalesChannelContext(Request $request, string $salesChannelId, string $contextToken): void
    {
        $language = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID);
        $currencyId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID);

        $context = $this->contextService
            ->get(new SalesChannelContextServiceParameters($salesChannelId, $contextToken, $language, $currencyId));

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
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
            throw CartException::customerNotLoggedIn();
        }

        if ($request->attributes->get(PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST, false) === false && $context->getCustomer()->getGuest()) {
            throw CartException::customerNotLoggedIn();
        }
    }
}
