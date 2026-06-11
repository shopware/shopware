<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Lets headless store-api clients resolve a request's language and currency from a configured sales channel
 * domain URL sent in the {@see PlatformRequest::HEADER_DOMAIN} header, instead of having to know the ids.
 * The explicit `sw-language-id` / `sw-currency-id` headers still take precedence.
 *
 * @internal
 */
#[Package('framework')]
class StoreApiDomainResolver implements EventSubscriberInterface
{
    use RouteScopeCheckTrait;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly SalesChannelContextPersister $contextPersister,
        private readonly RouteScopeRegistry $routeScopeRegistry
    ) {
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                'resolveDomain',
                KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_STORE_API_DOMAIN_RESOLVE,
            ],
        ];
    }

    public function resolveDomain(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        $domainUrl = trim((string) $request->headers->get(PlatformRequest::HEADER_DOMAIN, ''));
        if ($domainUrl === '') {
            return;
        }

        if (!$this->isRequestScoped($request, StoreApiRouteScope::class)) {
            return;
        }

        // an empty value counts as absent, matching how SalesChannelRequestContextResolver reads these headers
        $resolveLanguage = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID, '') === '';
        $resolveCurrency = $request->headers->get(PlatformRequest::HEADER_CURRENCY_ID, '') === '';

        if (!$resolveLanguage && !$resolveCurrency) {
            return;
        }

        $salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        if (!\is_string($salesChannelId) || $salesChannelId === '') {
            return;
        }

        $config = $this->fetchLanguageAndCurrency($salesChannelId, $domainUrl);

        if ($config === null) {
            throw RoutingException::salesChannelDomainNotFound($domainUrl);
        }

        if ($resolveLanguage && !$this->hasSwitchedLanguage($request, $salesChannelId)) {
            $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, $config['languageId']);
        }

        if ($resolveCurrency) {
            // default slot, not the sw-currency-id override: a currency switched in the context token still wins
            $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $config['currencyId']);
        }
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }

    private function hasSwitchedLanguage(Request $request, string $salesChannelId): bool
    {
        $token = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        if (!\is_string($token) || $token === '') {
            return false;
        }

        return \array_key_exists(
            SalesChannelContextService::LANGUAGE_ID,
            $this->contextPersister->load($token, $salesChannelId)
        );
    }

    /**
     * @return array{languageId: string, currencyId: string}|null the matched domain's configured language and currency
     */
    private function fetchLanguageAndCurrency(string $salesChannelId, string $domainUrl): ?array
    {
        $config = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(language_id)) AS languageId, LOWER(HEX(currency_id)) AS currencyId
             FROM sales_channel_domain
             WHERE sales_channel_id = :salesChannelId
               AND (url = :url OR url = CONCAT(:url, \'/\'))',
            [
                'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
                'url' => rtrim($domainUrl, '/'),
            ]
        );

        if ($config === false) {
            return null;
        }

        return [
            'languageId' => $config['languageId'],
            'currencyId' => $config['currencyId'],
        ];
    }
}
