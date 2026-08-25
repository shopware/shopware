<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\SalesChannel;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Cookie\CookieConsentLog\CookieConsentLogEntity;
use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Content\Cookie\Event\CookieConsentLoggedEvent;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
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
 * The client only reports raw facts: which action the visitor performed, which
 * cookies were ticked, and which configuration was on screen. Everything else,
 * especially the per-group verdict, is derived here against the configuration
 * the server holds, so the stored evidence cannot be shaped by the client and
 * the rules stay in one testable place.
 *
 * Alongside every log entry, a snapshot of the current cookie banner
 * configuration is stored once per configuration hash, preserving what the
 * banner looked like when the consent was given.
 *
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
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

    private const MAX_ACCEPTED_COOKIES = 500;
    private const MAX_STRING_LENGTH = 255;

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCookieRoute $cookieRoute,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    public function getDecorated(): AbstractCookieConsentLogRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/cookie-consent-log', name: 'store-api.cookie.consent-log', methods: [Request::METHOD_POST])]
    public function log(Request $request, SalesChannelContext $salesChannelContext): NoContentResponse
    {
        $this->ensureNotRateLimited($request);

        $payload = $this->validatePayload($request);

        $currentConfig = $this->cookieRoute->getCookieGroups($request, $salesChannelContext);
        $cookieGroups = $currentConfig->getCookieGroups();

        // The snapshot is always written for the configuration the server holds, so a log
        // entry can never reference a snapshot that does not exist. The hash the client
        // reports is stored next to it, unverified, as evidence of what was on screen.
        $serverConfigHash = $currentConfig->getHash();
        $renderedConfigHash = $payload['renderedConfigHash'] ?? null;

        $decisions = $this->deriveDecisions($cookieGroups, $payload['consentAction'], $payload['acceptedCookies']);

        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $languageId = $salesChannelContext->getLanguageId();

        // One transaction, so a log entry always resolves to a snapshot. The snapshot insert
        // is a no-op once the configuration is known, and the cleanup task deletes snapshots
        // no log entry references any more, so both statements have to commit together: the
        // duplicate insert holds a lock on the snapshot row until then, making the cleanup wait.
        // Retried on deadlock, because the consent beacon is fire-and-forget: a rolled back
        // transaction would drop the evidence with nobody left to send it again.
        RetryableTransaction::retryable($this->connection, function () use ($payload, $cookieGroups, $decisions, $serverConfigHash, $renderedConfigHash, $now, $salesChannelId, $languageId): void {
            $this->connection->executeStatement(
                'INSERT IGNORE INTO `cookie_consent_config_version`
                    (`id`, `config_hash`, `sales_channel_id`, `language_id`, `cookie_groups`, `created_at`)
                VALUES
                    (:id, :configHash, :salesChannelId, :languageId, :cookieGroups, :createdAt)',
                [
                    'id' => Uuid::randomBytes(),
                    'configHash' => $serverConfigHash,
                    'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
                    'languageId' => Uuid::fromHexToBytes($languageId),
                    'cookieGroups' => json_encode($cookieGroups, \JSON_THROW_ON_ERROR),
                    'createdAt' => $now,
                ],
            );

            $this->connection->executeStatement(
                'INSERT INTO `cookie_consent_log`
                    (`id`, `sales_channel_id`, `language_id`, `consent_action`, `group_decisions`, `accepted_cookies`, `server_config_hash`, `rendered_config_hash`, `created_at`)
                VALUES
                    (:id, :salesChannelId, :languageId, :consentAction, :groupDecisions, :acceptedCookies, :serverConfigHash, :renderedConfigHash, :createdAt)',
                [
                    'id' => Uuid::randomBytes(),
                    'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
                    'languageId' => Uuid::fromHexToBytes($languageId),
                    'consentAction' => $payload['consentAction'],
                    'groupDecisions' => json_encode($decisions['groupDecisions'], \JSON_THROW_ON_ERROR | \JSON_FORCE_OBJECT),
                    'acceptedCookies' => json_encode($decisions['acceptedCookies'], \JSON_THROW_ON_ERROR),
                    'serverConfigHash' => $serverConfigHash,
                    'renderedConfigHash' => $renderedConfigHash,
                    'createdAt' => $now,
                ],
            );
        });

        $this->eventDispatcher->dispatch(new CookieConsentLoggedEvent(
            consentAction: $payload['consentAction'],
            groupDecisions: $decisions['groupDecisions'],
            acceptedCookies: $decisions['acceptedCookies'],
            serverConfigHash: $serverConfigHash,
            renderedConfigHash: $renderedConfigHash,
            salesChannelId: $salesChannelId,
            languageId: $languageId,
        ));

        return new NoContentResponse();
    }

    /**
     * The route is anonymous and every accepted request inserts a row, so the number of
     * decisions a single client can write has to be capped. Checked before the payload is
     * parsed, so malformed requests count against the limit too. The IP is only the limiter
     * key, it is never stored with the decision.
     */
    private function ensureNotRateLimited(Request $request): void
    {
        $clientIp = $request->getClientIp();
        if ($clientIp === null) {
            return;
        }

        $this->rateLimiter->ensureAccepted(RateLimiter::COOKIE_CONSENT_LOG, $clientIp);
    }

    /**
     * Determines per group what the visitor actually consented to. Cookie names the
     * current configuration does not know are ignored, the log must not become a sink
     * for arbitrary client input.
     *
     * @param list<string> $requestedCookies
     *
     * @return array{groupDecisions: array<string, CookieConsentLogEntity::DECISION_*>, acceptedCookies: list<string>}
     */
    private function deriveDecisions(CookieGroupCollection $cookieGroups, string $consentAction, array $requestedCookies): array
    {
        $groupDecisions = [];
        $acceptedCookies = [];

        foreach ($cookieGroups as $group) {
            $technicalName = $group->getTechnicalName();

            // Required groups offer no choice, they are always active and are not consented to.
            if ($group->isRequired) {
                $groupDecisions[$technicalName] = CookieConsentLogEntity::DECISION_ACCEPTED;

                continue;
            }

            $selectable = $this->selectableCookies($group);

            $accepted = match ($consentAction) {
                self::ACTION_ACCEPT_ALL => $selectable,
                self::ACTION_ACCEPT_REQUIRED => [],
                default => array_values(array_intersect($selectable, $requestedCookies)),
            };

            // A group without selectable cookies presented nothing to consent to. It is recorded
            // as rejected, understating consent is the safe direction for an evidence log.
            $groupDecisions[$technicalName] = match (true) {
                $accepted === [] => CookieConsentLogEntity::DECISION_REJECTED,
                \count($accepted) === \count($selectable) => CookieConsentLogEntity::DECISION_ACCEPTED,
                default => CookieConsentLogEntity::DECISION_PARTIAL,
            };

            foreach ($accepted as $cookie) {
                $acceptedCookies[] = $cookie;
            }
        }

        return ['groupDecisions' => $groupDecisions, 'acceptedCookies' => $acceptedCookies];
    }

    /**
     * Cookies of a group the visitor can actually tick. Hidden entries are excluded:
     * they are never rendered, so counting them would mark every group as partial.
     *
     * @return list<string>
     */
    private function selectableCookies(CookieGroup $group): array
    {
        $cookie = $group->getCookie();
        if ($cookie !== null && $cookie !== '') {
            return [$cookie];
        }

        $selectable = [];
        foreach ($group->getEntries() ?? [] as $entry) {
            if ($entry->hidden || $entry->cookie === '') {
                continue;
            }

            $selectable[] = $entry->cookie;
        }

        return $selectable;
    }

    /**
     * The request body is parsed manually because the storefront sends it via
     * navigator.sendBeacon, which cannot guarantee a JSON content type header.
     *
     * @return array{consentAction: string, acceptedCookies: list<string>, renderedConfigHash?: string}
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

        $payload = [
            'consentAction' => $consentAction,
            'acceptedCookies' => $this->validateAcceptedCookies($data['acceptedCookies'] ?? []),
        ];

        $renderedConfigHash = $data['renderedConfigHash'] ?? null;
        if ($renderedConfigHash !== null) {
            if (!\is_string($renderedConfigHash) || $renderedConfigHash === '' || mb_strlen($renderedConfigHash) > self::MAX_STRING_LENGTH) {
                throw CookieException::invalidConsentLogPayload('renderedConfigHash must be a non-empty string');
            }

            $payload['renderedConfigHash'] = $renderedConfigHash;
        }

        return $payload;
    }

    /**
     * An absent list is a valid decision: the visitor may have unticked everything.
     * It is only relevant for `accept_selected`, the other actions are fully
     * determined by the action itself.
     *
     * @return list<string>
     */
    private function validateAcceptedCookies(mixed $acceptedCookies): array
    {
        if (!\is_array($acceptedCookies) || !array_is_list($acceptedCookies) || \count($acceptedCookies) > self::MAX_ACCEPTED_COOKIES) {
            throw CookieException::invalidConsentLogPayload(
                \sprintf('acceptedCookies must be a list with at most %d entries', self::MAX_ACCEPTED_COOKIES),
            );
        }

        foreach ($acceptedCookies as $cookie) {
            if (!\is_string($cookie) || $cookie === '' || mb_strlen($cookie) > self::MAX_STRING_LENGTH) {
                throw CookieException::invalidConsentLogPayload('acceptedCookies must contain non-empty strings');
            }
        }

        return $acceptedCookies;
    }
}
