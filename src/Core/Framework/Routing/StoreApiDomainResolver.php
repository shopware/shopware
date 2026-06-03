<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Resolves the language of a store-api request from the {@see PlatformRequest::HEADER_DOMAIN} header.
 *
 * Headless frontends are not served from a configured sales channel domain, so they cannot rely on the
 * domain-based language resolution the Storefront uses. By sending the URL of a configured sales channel domain
 * in the `sw-domain` header, such clients can have the request served with that domain's language without having
 * to know the language id.
 *
 * An explicit {@see PlatformRequest::HEADER_LANGUAGE_ID} header always takes precedence.
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

        // An explicit language header always wins over the domain-derived language.
        if ($request->headers->has(PlatformRequest::HEADER_LANGUAGE_ID)) {
            return;
        }

        $salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        if (!\is_string($salesChannelId) || $salesChannelId === '') {
            return;
        }

        $domainUrl = (string) $request->headers->get(PlatformRequest::HEADER_DOMAIN);

        $languageId = $this->fetchDomainLanguageId($salesChannelId, $domainUrl);

        if ($languageId === null) {
            throw RoutingException::salesChannelDomainNotFound($domainUrl);
        }

        $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, $languageId);
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }

    private function fetchDomainLanguageId(string $salesChannelId, string $domainUrl): ?string
    {
        $languageId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(language_id))
             FROM sales_channel_domain
             WHERE sales_channel_id = :salesChannelId
               AND TRIM(TRAILING \'/\' FROM url) = :url',
            [
                'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
                'url' => rtrim($domainUrl, '/'),
            ]
        );

        return \is_string($languageId) && $languageId !== '' ? $languageId : null;
    }
}
