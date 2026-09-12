<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\SalesChannel;

use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Cookie\ConsentLog\AbstractCookieConsentLogStorage;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentAction;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentConfigSnapshot;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentDecision;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Records cookie consent decisions of visitors so shop operators can demonstrate
 * that consent was obtained (GDPR Art. 7(1), Recital 42).
 *
 * The client only reports raw facts: its consent id, which action the visitor
 * performed and which cookies were ticked. Everything else, especially the
 * per-group verdict, is derived here against the configuration the server holds,
 * so the stored evidence cannot be shaped by the client and the rules stay in
 * one testable place.
 *
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CookieConsentLogRoute extends AbstractCookieConsentLogRoute
{
    private const MAX_ACCEPTED_COOKIES = 500;
    private const MAX_STRING_LENGTH = 255;

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCookieRoute $cookieRoute,
        private readonly AbstractCookieConsentLogStorage $storage,
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

        $configuration = $this->cookieRoute->getCookieGroups($request, $salesChannelContext);
        $cookieGroups = $configuration->getCookieGroups();
        $decisions = $this->deriveDecisions($cookieGroups, $payload['consentAction'], $payload['acceptedCookies']);
        $now = $this->clock->now();

        $record = new CookieConsentRecord(
            consentId: $payload['consentId'],
            consentAction: $payload['consentAction'],
            groupDecisions: $decisions['groupDecisions'],
            acceptedCookies: $decisions['acceptedCookies'],
            configHash: $configuration->getHash(),
            salesChannelId: $salesChannelContext->getSalesChannelId(),
            languageId: $salesChannelContext->getLanguageId(),
            createdAt: $now,
        );

        // The snapshot goes first, so a stored decision always resolves to the banner it was given on
        $this->storage->snapshot(new CookieConsentConfigSnapshot(
            configHash: $configuration->getHash(),
            cookieGroups: array_values($cookieGroups->getElements()),
            createdAt: $now,
        ));
        $this->storage->log($record);

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
     * @return array{groupDecisions: array<string, CookieConsentDecision>, acceptedCookies: list<string>}
     */
    private function deriveDecisions(CookieGroupCollection $cookieGroups, CookieConsentAction $consentAction, array $requestedCookies): array
    {
        $groupDecisions = [];
        $acceptedCookies = [];

        foreach ($cookieGroups as $group) {
            $technicalName = $group->getTechnicalName();

            // Required groups offer no choice, they are always active and are not consented to.
            if ($group->isRequired) {
                $groupDecisions[$technicalName] = CookieConsentDecision::ACCEPTED;

                continue;
            }

            $selectable = $this->selectableCookies($group);

            $accepted = match ($consentAction) {
                CookieConsentAction::ACCEPT_ALL => $selectable,
                CookieConsentAction::ACCEPT_REQUIRED => [],
                CookieConsentAction::ACCEPT_SELECTED => array_values(array_intersect($selectable, $requestedCookies)),
            };

            // A group without selectable cookies presented nothing to consent to. It is recorded
            // as rejected, understating consent is the safe direction for an evidence log.
            $groupDecisions[$technicalName] = match (true) {
                $accepted === [] => CookieConsentDecision::REJECTED,
                \count($accepted) === \count($selectable) => CookieConsentDecision::ACCEPTED,
                default => CookieConsentDecision::PARTIAL,
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
     * @return array{consentId: string, consentAction: CookieConsentAction, acceptedCookies: list<string>}
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

        $consentId = $data['consentId'] ?? null;
        if (!\is_string($consentId) || preg_match(CookieConsentRecord::CONSENT_ID_PATTERN, $consentId) !== 1) {
            throw CookieException::invalidConsentLogPayload('consentId must be a string of 1 to 64 letters, digits, dashes or underscores');
        }

        $consentAction = \is_string($data['consentAction'] ?? null) ? CookieConsentAction::tryFrom($data['consentAction']) : null;
        if ($consentAction === null) {
            throw CookieException::invalidConsentLogPayload(
                \sprintf('consentAction must be one of: %s', implode(', ', array_column(CookieConsentAction::cases(), 'value'))),
            );
        }

        return [
            'consentId' => $consentId,
            'consentAction' => $consentAction,
            'acceptedCookies' => $this->validateAcceptedCookies($data['acceptedCookies'] ?? []),
        ];
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
