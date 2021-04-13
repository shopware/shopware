<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Framework\Routing\Annotation\ContextTokenRequired;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class SalesChannelRequestContextResolver implements RequestContextResolverInterface
{
    use RouteScopeCheckTrait;

    /**
     * @var RequestContextResolverInterface
     */
    private $decorated;

    /**
     * @var SalesChannelContextServiceInterface
     */
    private $contextService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SalesChannelContext[]
     */
    private $cache = [];

    /**
     * @var RouteScopeRegistry
     */
    private $routeScopeRegistry;

    public function __construct(
        RequestContextResolverInterface $decorated,
        SalesChannelContextServiceInterface $contextService,
        EventDispatcherInterface $eventDispatcher,
        RouteScopeRegistry $routeScopeRegistry
    ) {
        $this->decorated = $decorated;
        $this->contextService = $contextService;
        $this->eventDispatcher = $eventDispatcher;
        $this->routeScopeRegistry = $routeScopeRegistry;
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

        if (
            $this->contextTokenRequired($request) === true
            && !$request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN)
        ) {
            throw new MissingRequestParameterException(PlatformRequest::HEADER_CONTEXT_TOKEN);
        }

        if (
            $this->contextTokenRequired($request) === false
            && !$request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN)
        ) {
            $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, Random::getAlphanumericString(32));
        }

        $contextToken = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        $language = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID);
        $currencyId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID);
        $domainId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID);

        $cacheKey = $salesChannelId . $contextToken . $language . $currencyId . $domainId;

        if (!empty($this->cache[$cacheKey])) {
            $context = $this->cache[$cacheKey];
        } else {
            $context = $this->contextService->get(
                new SalesChannelContextServiceParameters((string) $salesChannelId, (string) $contextToken, $language, $currencyId, $domainId)
            );
            $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $context->getToken());
        }

        $this->validateLogin($request, $context);

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $context->getToken());

        $this->eventDispatcher->dispatch(
            new SalesChannelContextResolvedEvent($context, (string) $contextToken)
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
        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED)) {
            return false;
        }

        /** @var ContextTokenRequired $contextTokenRequiredAnnotation */
        $contextTokenRequiredAnnotation = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED);

        return $contextTokenRequiredAnnotation->isRequired();
    }

    private function validateLogin(Request $request, SalesChannelContext $context): void
    {
        /** @var LoginRequired|null $loginRequired */
        $loginRequired = $request->attributes->get(PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED);

        if ($loginRequired === null) {
            return;
        }

        if ($loginRequired->isLoggedIn($context)) {
            return;
        }

        throw new CustomerNotLoggedInException();
    }
}
