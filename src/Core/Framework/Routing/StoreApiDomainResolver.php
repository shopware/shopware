<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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

        if (!$request->headers->has(PlatformRequest::HEADER_DOMAIN)) {
            return;
        }

        if (!$this->isRequestScoped($request, StoreApiRouteScope::class)) {
            return;
        }

        $resolveLanguage = !$request->headers->has(PlatformRequest::HEADER_LANGUAGE_ID);
        $resolveCurrency = !$request->headers->has(PlatformRequest::HEADER_CURRENCY_ID);

        if (!$resolveLanguage && !$resolveCurrency) {
            return;
        }

        $salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        if (!\is_string($salesChannelId) || $salesChannelId === '') {
            return;
        }

        $domainUrl = (string) $request->headers->get(PlatformRequest::HEADER_DOMAIN);

        $domain = $this->fetchDomain($salesChannelId, $domainUrl);

        if ($domain === null) {
            throw RoutingException::salesChannelDomainNotFound($domainUrl);
        }

        if ($resolveLanguage) {
            $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, $domain['languageId']);
        }

        if ($resolveCurrency) {
            // default slot, not the sw-currency-id override: a currency switched in the context token still wins
            $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $domain['currencyId']);
        }
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }

    /**
     * @return array{languageId: string, currencyId: string}|null
     */
    private function fetchDomain(string $salesChannelId, string $domainUrl): ?array
    {
        $domain = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(language_id)) AS languageId, LOWER(HEX(currency_id)) AS currencyId
             FROM sales_channel_domain
             WHERE sales_channel_id = :salesChannelId
               AND TRIM(TRAILING \'/\' FROM url) = :url',
            [
                'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
                'url' => rtrim($domainUrl, '/'),
            ]
        );

        if ($domain === false) {
            return null;
        }

        return [
            'languageId' => (string) $domain['languageId'],
            'currencyId' => (string) $domain['currencyId'],
        ];
    }
}
