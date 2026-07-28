<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\SalesChannel;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Content\Cookie\Event\CookieConsentLoggedEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Persists anonymous cookie consent decisions of storefront visitors so shop
 * operators can demonstrate that consent was obtained (GDPR Recital 42).
 *
 * Alongside every log entry, a snapshot of the current cookie banner
 * configuration is stored once per configuration hash, preserving what the
 * banner looked like when the consent was given.
 *
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('framework')]
class CookieConsentLogRoute extends AbstractCookieConsentLogRoute
{
    final public const ACTION_ACCEPT_ALL = 'accept_all';
    final public const ACTION_ACCEPT_REQUIRED = 'accept_required';
    final public const ACTION_ACCEPT_SELECTED = 'accept_selected';

    private const VALID_ACTIONS = [
        self::ACTION_ACCEPT_ALL,
        self::ACTION_ACCEPT_REQUIRED,
        self::ACTION_ACCEPT_SELECTED,
    ];

    private const MAX_ACCEPTED_GROUPS = 100;
    private const MAX_STRING_LENGTH = 255;

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCookieRoute $cookieRoute,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getDecorated(): AbstractCookieConsentLogRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/cookie-consent-log', name: 'store-api.cookie.consent-log', methods: [Request::METHOD_POST])]
    public function log(Request $request, SalesChannelContext $salesChannelContext): NoContentResponse
    {
        $payload = $this->validatePayload($request);

        $currentConfig = $this->cookieRoute->getCookieGroups($request, $salesChannelContext);

        // The client sends the hash of the configuration it rendered. It normally matches the
        // current one, but may be stale when the banner changed after page load. The log entry
        // keeps the client hash as evidence of what the visitor actually saw.
        $configHash = $payload['cookieConfigHash'] ?? $currentConfig->getHash();

        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $languageId = $salesChannelContext->getLanguageId();

        $this->connection->transactional(function () use ($payload, $currentConfig, $configHash, $now, $salesChannelId, $languageId): void {
            $this->connection->executeStatement(
                'INSERT IGNORE INTO `cookie_consent_config_version`
                    (`id`, `config_hash`, `sales_channel_id`, `language_id`, `cookie_groups`, `created_at`)
                VALUES
                    (:id, :configHash, :salesChannelId, :languageId, :cookieGroups, :createdAt)',
                [
                    'id' => Uuid::randomBytes(),
                    'configHash' => $currentConfig->getHash(),
                    'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
                    'languageId' => Uuid::fromHexToBytes($languageId),
                    'cookieGroups' => json_encode($currentConfig->getCookieGroups(), \JSON_THROW_ON_ERROR),
                    'createdAt' => $now,
                ],
            );

            $this->connection->executeStatement(
                'INSERT INTO `cookie_consent_log`
                    (`id`, `sales_channel_id`, `language_id`, `consent_action`, `accepted_groups`, `config_hash`, `created_at`)
                VALUES
                    (:id, :salesChannelId, :languageId, :consentAction, :acceptedGroups, :configHash, :createdAt)',
                [
                    'id' => Uuid::randomBytes(),
                    'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
                    'languageId' => Uuid::fromHexToBytes($languageId),
                    'consentAction' => $payload['consentAction'],
                    'acceptedGroups' => json_encode($payload['acceptedGroups'], \JSON_THROW_ON_ERROR),
                    'configHash' => $configHash,
                    'createdAt' => $now,
                ],
            );
        });

        $this->eventDispatcher->dispatch(new CookieConsentLoggedEvent(
            consentAction: $payload['consentAction'],
            acceptedGroups: $payload['acceptedGroups'],
            configHash: $configHash,
            salesChannelId: $salesChannelId,
            languageId: $languageId,
        ));

        return new NoContentResponse();
    }

    /**
     * The request body is parsed manually because the storefront sends it via
     * navigator.sendBeacon, which cannot guarantee a JSON content type header.
     *
     * @return array{consentAction: string, acceptedGroups: list<string>, cookieConfigHash?: string}
     */
    private function validatePayload(Request $request): array
    {
        try {
            $data = json_decode($request->getContent(), true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw CookieException::invalidConsentLogPayload('body must be valid JSON');
        }

        if (!\is_array($data)) {
            throw CookieException::invalidConsentLogPayload('body must be a JSON object');
        }

        $consentAction = $data['consentAction'] ?? null;
        if (!\is_string($consentAction) || !\in_array($consentAction, self::VALID_ACTIONS, true)) {
            throw CookieException::invalidConsentLogPayload(
                \sprintf('consentAction must be one of: %s', implode(', ', self::VALID_ACTIONS)),
            );
        }

        $acceptedGroups = $data['acceptedGroups'] ?? null;
        if (!\is_array($acceptedGroups) || !array_is_list($acceptedGroups) || \count($acceptedGroups) > self::MAX_ACCEPTED_GROUPS) {
            throw CookieException::invalidConsentLogPayload(
                \sprintf('acceptedGroups must be a list with at most %d entries', self::MAX_ACCEPTED_GROUPS),
            );
        }

        foreach ($acceptedGroups as $group) {
            if (!\is_string($group) || $group === '' || mb_strlen($group) > self::MAX_STRING_LENGTH) {
                throw CookieException::invalidConsentLogPayload('acceptedGroups must contain non-empty strings');
            }
        }

        $payload = [
            'consentAction' => $consentAction,
            'acceptedGroups' => $acceptedGroups,
        ];

        $cookieConfigHash = $data['cookieConfigHash'] ?? null;
        if ($cookieConfigHash !== null) {
            if (!\is_string($cookieConfigHash) || $cookieConfigHash === '' || mb_strlen($cookieConfigHash) > self::MAX_STRING_LENGTH) {
                throw CookieException::invalidConsentLogPayload('cookieConfigHash must be a non-empty string');
            }

            $payload['cookieConfigHash'] = $cookieConfigHash;
        }

        return $payload;
    }
}
