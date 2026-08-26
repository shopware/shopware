<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\CookieConsentLog\CookieConsentLogEntity;
use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Content\Cookie\Event\CookieConsentLoggedEvent;
use Shopware\Core\Content\Cookie\SalesChannel\AbstractCookieRoute;
use Shopware\Core\Content\Cookie\SalesChannel\CookieConsentLogRoute;
use Shopware\Core\Content\Cookie\SalesChannel\CookieRouteResponse;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\RateLimiter\RateLimiterException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CookieConsentLogRoute::class)]
class CookieConsentLogRouteTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private CookieConsentLogRoute $route;

    private RateLimiter&Stub $rateLimiter;

    private SystemConfigService&Stub $systemConfigService;

    /**
     * @var list<array<string, mixed>>
     */
    private array $insertedParameters = [];

    protected function setUp(): void
    {
        $cookieRoute = static::createStub(AbstractCookieRoute::class);
        $cookieRoute->method('getCookieGroups')
            ->willReturn(new CookieRouteResponse($this->cookieGroups(), 'server-hash', 'language-id'));

        $connection = static::createStub(Connection::class);
        $connection->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback($connection));
        $connection->method('executeStatement')
            ->willReturnCallback(function (string $_query, array $parameters = []): int {
                $this->insertedParameters[] = $parameters;

                return 1;
            });

        $this->eventDispatcher = new CollectingEventDispatcher();

        $this->rateLimiter = static::createStub(RateLimiter::class);

        $this->systemConfigService = static::createStub(SystemConfigService::class);
        $this->systemConfigService->method('get')->willReturn(true);

        $this->route = new CookieConsentLogRoute(
            $cookieRoute,
            $connection,
            $this->eventDispatcher,
            new MockClock('2026-07-13 12:00:00'),
            $this->rateLimiter,
            $this->systemConfigService,
        );
    }

    public function testItThrowsDecorationPatternException(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(CookieConsentLogRoute::class));

        $this->route->getDecorated();
    }

    public function testAcceptAllMarksEveryGroupAccepted(): void
    {
        $event = $this->log(['consentAction' => 'accept_all']);

        static::assertSame([
            'cookie.groupRequired' => CookieConsentLogEntity::DECISION_ACCEPTED,
            'cookie.groupStatistical' => CookieConsentLogEntity::DECISION_ACCEPTED,
            'cookie.groupMarketing' => CookieConsentLogEntity::DECISION_ACCEPTED,
            'cookie.groupComfort' => CookieConsentLogEntity::DECISION_ACCEPTED,
        ], $event->groupDecisions);
        static::assertSame(['lorem', 'ipsum', 'marketing-cookie', 'visible-comfort'], $event->acceptedCookies);
    }

    public function testAcceptRequiredRejectsEveryOptionalGroup(): void
    {
        $event = $this->log(['consentAction' => 'accept_required']);

        static::assertSame([
            'cookie.groupRequired' => CookieConsentLogEntity::DECISION_ACCEPTED,
            'cookie.groupStatistical' => CookieConsentLogEntity::DECISION_REJECTED,
            'cookie.groupMarketing' => CookieConsentLogEntity::DECISION_REJECTED,
            'cookie.groupComfort' => CookieConsentLogEntity::DECISION_REJECTED,
        ], $event->groupDecisions);
        static::assertSame([], $event->acceptedCookies);
    }

    public function testPartiallySelectedGroupIsNotRecordedAsAccepted(): void
    {
        $event = $this->log([
            'consentAction' => 'accept_selected',
            'acceptedCookies' => ['ipsum'],
        ]);

        static::assertSame([
            'cookie.groupRequired' => CookieConsentLogEntity::DECISION_ACCEPTED,
            'cookie.groupStatistical' => CookieConsentLogEntity::DECISION_PARTIAL,
            'cookie.groupMarketing' => CookieConsentLogEntity::DECISION_REJECTED,
            'cookie.groupComfort' => CookieConsentLogEntity::DECISION_REJECTED,
        ], $event->groupDecisions);
        static::assertSame(['ipsum'], $event->acceptedCookies);
    }

    public function testFullySelectedGroupIsRecordedAsAccepted(): void
    {
        $event = $this->log([
            'consentAction' => 'accept_selected',
            'acceptedCookies' => ['lorem', 'ipsum'],
        ]);

        static::assertSame(CookieConsentLogEntity::DECISION_ACCEPTED, $event->groupDecisions['cookie.groupStatistical']);
    }

    public function testStandaloneGroupCookieIsResolved(): void
    {
        $event = $this->log([
            'consentAction' => 'accept_selected',
            'acceptedCookies' => ['marketing-cookie'],
        ]);

        static::assertSame(CookieConsentLogEntity::DECISION_ACCEPTED, $event->groupDecisions['cookie.groupMarketing']);
        static::assertSame(['marketing-cookie'], $event->acceptedCookies);
    }

    public function testHiddenEntriesDoNotPreventAFullAcceptance(): void
    {
        // `cookie.groupComfort` only has a hidden entry next to a visible one, the
        // visitor can never tick the hidden one
        $event = $this->log([
            'consentAction' => 'accept_selected',
            'acceptedCookies' => ['visible-comfort'],
        ]);

        static::assertSame(CookieConsentLogEntity::DECISION_ACCEPTED, $event->groupDecisions['cookie.groupComfort']);
        static::assertNotContains('hidden-comfort', $event->acceptedCookies);
    }

    public function testUnknownCookieNamesAreIgnored(): void
    {
        $event = $this->log([
            'consentAction' => 'accept_selected',
            'acceptedCookies' => ['ipsum', 'injected-by-a-client'],
        ]);

        static::assertSame(['ipsum'], $event->acceptedCookies);
    }

    public function testRenderedHashIsStoredNextToTheServerHash(): void
    {
        $event = $this->log([
            'consentAction' => 'accept_all',
            'renderedConfigHash' => 'stale-hash',
        ]);

        static::assertSame('server-hash', $event->serverConfigHash);
        static::assertSame('stale-hash', $event->renderedConfigHash);

        // The snapshot is always written for the hash the server holds, so the log entry
        // can never point at a snapshot that does not exist
        static::assertSame('server-hash', $this->insertedParameters[0]['configHash']);
        static::assertSame('server-hash', $this->insertedParameters[1]['serverConfigHash']);
        static::assertSame('stale-hash', $this->insertedParameters[1]['renderedConfigHash']);
    }

    public function testRenderedHashIsNullWhenNoConfigurationWasDisplayed(): void
    {
        $event = $this->log(['consentAction' => 'accept_all']);

        static::assertNull($event->renderedConfigHash);
    }

    public function testLogReturnsNoContentAndDispatchesOnce(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $request = new Request(content: (string) json_encode(['consentAction' => 'accept_all']));
        $response = $this->route->log($request, $salesChannelContext);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertCount(1, $this->eventDispatcher->getEvents());

        $event = $this->eventDispatcher->getEvents()[0];
        static::assertInstanceOf(CookieConsentLoggedEvent::class, $event);
        static::assertSame($salesChannelContext->getSalesChannelId(), $event->salesChannelId);
        static::assertSame($salesChannelContext->getLanguageId(), $event->languageId);
    }

    public function testGroupDecisionsAreStoredAsAJsonObject(): void
    {
        $this->log(['consentAction' => 'accept_required']);

        static::assertSame(
            '{"cookie.groupRequired":"accepted","cookie.groupStatistical":"rejected","cookie.groupMarketing":"rejected","cookie.groupComfort":"rejected"}',
            $this->insertedParameters[1]['groupDecisions'],
        );
    }

    public function testLogThrowsOnInvalidJsonBody(): void
    {
        $this->expectExceptionObject(CookieException::invalidConsentLogPayload('body must be valid JSON'));

        $this->route->log(new Request(content: 'no-json{'), Generator::generateSalesChannelContext());
    }

    public function testLogThrowsWhenBodyIsNoObject(): void
    {
        $this->expectExceptionObject(CookieException::invalidConsentLogPayload('body must be a JSON object'));

        $this->route->log(new Request(content: '"a-string"'), Generator::generateSalesChannelContext());
    }

    public function testLogThrowsOnUnknownConsentAction(): void
    {
        $this->expectExceptionObject(CookieException::invalidConsentLogPayload(
            'consentAction must be one of: accept_all, accept_required, accept_selected',
        ));

        $this->log(['consentAction' => 'reject_all']);
    }

    public function testMissingAcceptedCookiesIsAValidEmptySelection(): void
    {
        $event = $this->log(['consentAction' => 'accept_selected']);

        static::assertSame([], $event->acceptedCookies);
        static::assertSame(CookieConsentLogEntity::DECISION_REJECTED, $event->groupDecisions['cookie.groupStatistical']);
    }

    public function testLogThrowsWhenAcceptedCookiesIsNoList(): void
    {
        $this->expectExceptionObject(CookieException::invalidConsentLogPayload(
            'acceptedCookies must be a list with at most 500 entries',
        ));

        $this->log(['consentAction' => 'accept_selected', 'acceptedCookies' => ['key' => 'value']]);
    }

    public function testLogThrowsWhenAcceptedCookiesContainsNonStrings(): void
    {
        $this->expectExceptionObject(CookieException::invalidConsentLogPayload(
            'acceptedCookies must contain non-empty strings',
        ));

        $this->log(['consentAction' => 'accept_selected', 'acceptedCookies' => ['lorem', 42]]);
    }

    public function testLogThrowsWhenRenderedConfigHashIsNoString(): void
    {
        $this->expectExceptionObject(CookieException::invalidConsentLogPayload(
            'renderedConfigHash must be a non-empty string',
        ));

        $this->log(['consentAction' => 'accept_all', 'renderedConfigHash' => ['not' => 'a-string']]);
    }

    public function testASwitchedOffLogStoresNothing(): void
    {
        $route = $this->createRouteWithLogEnabled(false);

        $response = $route->log(
            new Request(content: (string) json_encode(['consentAction' => 'accept_all'])),
            Generator::generateSalesChannelContext(),
        );

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame([], $this->insertedParameters);
        static::assertSame([], $this->eventDispatcher->getEvents());
    }

    public function testAnUnsetSwitchKeepsTheLogEnabled(): void
    {
        // The setting is seeded by migration, a missing row must not stop collecting evidence
        $route = $this->createRouteWithLogEnabled(null);

        $route->log(
            new Request(content: (string) json_encode(['consentAction' => 'accept_all'])),
            Generator::generateSalesChannelContext(),
        );

        static::assertCount(1, $this->eventDispatcher->getEvents());
    }

    public function testTheClientIpIsUsedAsRateLimitKey(): void
    {
        $rateLimiter = static::createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::COOKIE_CONSENT_LOG, '203.0.113.7');

        $request = new Request(
            server: ['REMOTE_ADDR' => '203.0.113.7'],
            content: (string) json_encode(['consentAction' => 'accept_all']),
        );

        $this->createRoute($rateLimiter)->log($request, Generator::generateSalesChannelContext());
    }

    public function testAnExceededRateLimitStoresNothing(): void
    {
        $this->rateLimiter->method('ensureAccepted')
            ->willThrowException(RateLimiterException::limitExceeded(2_000_000_000));

        $request = new Request(
            server: ['REMOTE_ADDR' => '203.0.113.7'],
            content: (string) json_encode(['consentAction' => 'accept_all']),
        );

        $this->expectException(RateLimiterException::class);

        try {
            $this->route->log($request, Generator::generateSalesChannelContext());
        } finally {
            static::assertSame([], $this->insertedParameters);
            static::assertSame([], $this->eventDispatcher->getEvents());
        }
    }

    public function testTheRateLimitIsCheckedBeforeThePayloadIsParsed(): void
    {
        $this->rateLimiter->method('ensureAccepted')
            ->willThrowException(RateLimiterException::limitExceeded(2_000_000_000));

        // A malformed body must not buy a free request past the limiter
        $request = new Request(server: ['REMOTE_ADDR' => '203.0.113.7'], content: 'no-json{');

        $this->expectException(RateLimiterException::class);

        $this->route->log($request, Generator::generateSalesChannelContext());
    }

    public function testARequestWithoutAClientIpIsNotRateLimited(): void
    {
        // The limiter is keyed by IP only, so a request without one cannot be attributed
        $rateLimiter = static::createMock(RateLimiter::class);
        $rateLimiter->expects($this->never())->method('ensureAccepted');

        $request = new Request(content: (string) json_encode(['consentAction' => 'accept_all']));
        $response = $this->createRoute($rateLimiter)->log($request, Generator::generateSalesChannelContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * Builds an isolated route for tests that place expectations on the rate limiter,
     * so the shared stub in setUp() never mixes stub and mock roles.
     */
    private function createRouteWithLogEnabled(?bool $enabled, ?RateLimiter $rateLimiter = null): CookieConsentLogRoute
    {
        $cookieRoute = static::createStub(AbstractCookieRoute::class);
        $cookieRoute->method('getCookieGroups')
            ->willReturn(new CookieRouteResponse($this->cookieGroups(), 'server-hash', 'language-id'));

        $connection = static::createStub(Connection::class);
        $connection->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback($connection));
        $connection->method('executeStatement')
            ->willReturnCallback(function (string $_query, array $parameters = []): int {
                $this->insertedParameters[] = $parameters;

                return 1;
            });

        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn($enabled);

        return new CookieConsentLogRoute(
            $cookieRoute,
            $connection,
            $this->eventDispatcher,
            new MockClock('2026-07-13 12:00:00'),
            $rateLimiter ?? static::createStub(RateLimiter::class),
            $systemConfigService,
        );
    }

    private function createRoute(RateLimiter $rateLimiter): CookieConsentLogRoute
    {
        $cookieRoute = static::createStub(AbstractCookieRoute::class);
        $cookieRoute->method('getCookieGroups')
            ->willReturn(new CookieRouteResponse($this->cookieGroups(), 'server-hash', 'language-id'));

        $connection = static::createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(1);

        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn(true);

        return new CookieConsentLogRoute(
            $cookieRoute,
            $connection,
            new CollectingEventDispatcher(),
            new MockClock('2026-07-13 12:00:00'),
            $rateLimiter,
            $systemConfigService,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function log(array $payload): CookieConsentLoggedEvent
    {
        $request = new Request(content: (string) json_encode($payload));

        $this->route->log($request, Generator::generateSalesChannelContext());

        $event = $this->eventDispatcher->getEvents()[0];
        static::assertInstanceOf(CookieConsentLoggedEvent::class, $event);

        return $event;
    }

    private function cookieGroups(): CookieGroupCollection
    {
        $required = new CookieGroup('cookie.groupRequired');
        $required->isRequired = true;
        $required->setEntries(new CookieEntryCollection([new CookieEntry('session-')]));

        $statistical = new CookieGroup('cookie.groupStatistical');
        $statistical->setEntries(new CookieEntryCollection([new CookieEntry('lorem'), new CookieEntry('ipsum')]));

        // A group can be a standalone cookie instead of a list of entries
        $marketing = new CookieGroup('cookie.groupMarketing');
        $marketing->setCookie('marketing-cookie');

        $hidden = new CookieEntry('hidden-comfort');
        $hidden->hidden = true;

        $comfort = new CookieGroup('cookie.groupComfort');
        $comfort->setEntries(new CookieEntryCollection([new CookieEntry('visible-comfort'), $hidden]));

        return new CookieGroupCollection([$required, $statistical, $marketing, $comfort]);
    }
}
