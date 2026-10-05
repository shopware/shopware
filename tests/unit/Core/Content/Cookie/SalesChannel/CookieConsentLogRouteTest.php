<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\ConsentLog\AbstractCookieConsentLogStorage;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentAction;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentConfigSnapshot;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentDecision;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use Shopware\Core\Content\Cookie\SalesChannel\AbstractCookieRoute;
use Shopware\Core\Content\Cookie\SalesChannel\CookieConsentLogPayload;
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
use Shopware\Core\Test\Generator;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
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
    private const CONSENT_ID = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

    private InMemoryCookieConsentLogStorage $storage;

    private RateLimiter&Stub $rateLimiter;

    private CookieConsentLogRoute $route;

    protected function setUp(): void
    {
        $this->storage = new InMemoryCookieConsentLogStorage();
        $this->rateLimiter = static::createStub(RateLimiter::class);

        $this->route = $this->createRoute($this->rateLimiter);
    }

    public function testItThrowsDecorationPatternException(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(CookieConsentLogRoute::class));

        $this->route->getDecorated();
    }

    public function testAcceptAllMarksEveryGroupAccepted(): void
    {
        $record = $this->log(['consentAction' => 'accept_all']);

        static::assertSame(CookieConsentAction::ACCEPT_ALL, $record->consentAction);
        static::assertSame([
            'cookie.groupRequired' => CookieConsentDecision::ACCEPTED,
            'cookie.groupStatistical' => CookieConsentDecision::ACCEPTED,
            'cookie.groupMarketing' => CookieConsentDecision::ACCEPTED,
            'cookie.groupComfort' => CookieConsentDecision::ACCEPTED,
        ], $record->groupDecisions);
        static::assertSame(['lorem', 'ipsum', 'marketing-cookie', 'visible-comfort'], $record->acceptedCookies);
    }

    public function testAcceptRequiredRejectsEveryOptionalGroup(): void
    {
        $record = $this->log(['consentAction' => 'accept_required']);

        static::assertSame([
            'cookie.groupRequired' => CookieConsentDecision::ACCEPTED,
            'cookie.groupStatistical' => CookieConsentDecision::REJECTED,
            'cookie.groupMarketing' => CookieConsentDecision::REJECTED,
            'cookie.groupComfort' => CookieConsentDecision::REJECTED,
        ], $record->groupDecisions);
        static::assertSame([], $record->acceptedCookies);
    }

    public function testPartiallySelectedGroupIsNotRecordedAsAccepted(): void
    {
        $record = $this->log([
            'consentAction' => 'accept_selected',
            'acceptedCookies' => ['ipsum'],
        ]);

        static::assertSame([
            'cookie.groupRequired' => CookieConsentDecision::ACCEPTED,
            'cookie.groupStatistical' => CookieConsentDecision::PARTIAL,
            'cookie.groupMarketing' => CookieConsentDecision::REJECTED,
            'cookie.groupComfort' => CookieConsentDecision::REJECTED,
        ], $record->groupDecisions);
        static::assertSame(['ipsum'], $record->acceptedCookies);
    }

    public function testFullySelectedGroupIsRecordedAsAccepted(): void
    {
        $record = $this->log([
            'consentAction' => 'accept_selected',
            'acceptedCookies' => ['lorem', 'ipsum'],
        ]);

        static::assertSame(CookieConsentDecision::ACCEPTED, $record->groupDecisions['cookie.groupStatistical']);
    }

    public function testStandaloneGroupCookieIsResolved(): void
    {
        $record = $this->log([
            'consentAction' => 'accept_selected',
            'acceptedCookies' => ['marketing-cookie'],
        ]);

        static::assertSame(CookieConsentDecision::ACCEPTED, $record->groupDecisions['cookie.groupMarketing']);
        static::assertSame(['marketing-cookie'], $record->acceptedCookies);
    }

    public function testHiddenEntriesDoNotPreventAFullAcceptance(): void
    {
        // `cookie.groupComfort` only has a hidden entry next to a visible one, the
        // visitor can never tick the hidden one
        $record = $this->log([
            'consentAction' => 'accept_selected',
            'acceptedCookies' => ['visible-comfort'],
        ]);

        static::assertSame(CookieConsentDecision::ACCEPTED, $record->groupDecisions['cookie.groupComfort']);
        static::assertNotContains('hidden-comfort', $record->acceptedCookies);
    }

    public function testUnknownCookieNamesAreIgnored(): void
    {
        $record = $this->log([
            'consentAction' => 'accept_selected',
            'acceptedCookies' => ['ipsum', 'injected-by-a-client'],
        ]);

        static::assertSame(['ipsum'], $record->acceptedCookies);
    }

    public function testTheRecordCarriesTheContextOfTheDecision(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $this->route->log($this->payload(['consentAction' => 'accept_all']), new Request(), $salesChannelContext);

        $record = $this->storage->records[0];
        static::assertSame(self::CONSENT_ID, $record->consentId);
        static::assertSame('server-hash', $record->configHash);
        static::assertSame($salesChannelContext->getSalesChannelId(), $record->salesChannelId);
        static::assertSame($salesChannelContext->getLanguageId(), $record->languageId);
        static::assertSame('2026-07-13 12:00:00', $record->createdAt->format('Y-m-d H:i:s'));
    }

    public function testTheBannerSnapshotIsWrittenBeforeTheDecision(): void
    {
        $this->log(['consentAction' => 'accept_all']);

        static::assertSame(['snapshot', 'log'], $this->storage->calls);

        $snapshot = $this->storage->snapshots[0];
        static::assertSame('server-hash', $snapshot->configHash);
        static::assertSame(
            ['cookie.groupRequired', 'cookie.groupStatistical', 'cookie.groupMarketing', 'cookie.groupComfort'],
            array_values($snapshot->cookieGroups->map(static fn (CookieGroup $group) => $group->getTechnicalName())),
        );
        static::assertSame('2026-07-13 12:00:00', $snapshot->createdAt->format('Y-m-d H:i:s'));
    }

    public function testAKnownBannerIsOnlySnapshottedOnce(): void
    {
        $this->log(['consentAction' => 'accept_all']);
        $this->log(['consentAction' => 'accept_required']);

        static::assertSame(['snapshot', 'log', 'log'], $this->storage->calls);
    }

    public function testAFailedSnapshotIsTriedAgainWithTheNextDecision(): void
    {
        $this->storage->failNextSnapshot = new \RuntimeException('storage unavailable');

        try {
            $this->log(['consentAction' => 'accept_all']);
            static::fail('The failed snapshot must stop the decision');
        } catch (\RuntimeException) {
        }

        $this->log(['consentAction' => 'accept_all']);

        static::assertSame(['snapshot', 'snapshot', 'log'], $this->storage->calls);
        static::assertCount(1, $this->storage->snapshots);
    }

    public function testAWithdrawalIsRecordedUnderTheSameConsentId(): void
    {
        $this->log(['consentAction' => 'accept_all']);
        $this->log(['consentAction' => 'accept_selected', 'acceptedCookies' => ['lorem']]);

        static::assertCount(2, $this->storage->records);
        [$consent, $withdrawal] = $this->storage->records;

        static::assertSame($consent->consentId, $withdrawal->consentId);
        static::assertSame(CookieConsentDecision::ACCEPTED, $consent->groupDecisions['cookie.groupStatistical']);
        static::assertSame(CookieConsentDecision::PARTIAL, $withdrawal->groupDecisions['cookie.groupStatistical']);
        static::assertSame(CookieConsentDecision::REJECTED, $withdrawal->groupDecisions['cookie.groupMarketing']);
        static::assertSame(['lorem'], $withdrawal->acceptedCookies);
    }

    public function testLogReturnsNoContent(): void
    {
        $response = $this->route->log($this->payload(['consentAction' => 'accept_all']), new Request(), Generator::generateSalesChannelContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertCount(1, $this->storage->records);
    }

    public function testMissingAcceptedCookiesIsAValidEmptySelection(): void
    {
        $record = $this->log(['consentAction' => 'accept_selected']);

        static::assertSame([], $record->acceptedCookies);
        static::assertSame(CookieConsentDecision::REJECTED, $record->groupDecisions['cookie.groupStatistical']);
    }

    public function testTheClientIpIsUsedAsRateLimitKey(): void
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::COOKIE_CONSENT_LOG, '203.0.113.7');

        $this->createRoute($rateLimiter)->log($this->payload(['consentAction' => 'accept_all']), $this->request('203.0.113.7'), Generator::generateSalesChannelContext());
    }

    public function testAnExceededRateLimitStoresNothing(): void
    {
        $this->rateLimiter->method('ensureAccepted')
            ->willThrowException(RateLimiterException::limitExceeded(2_000_000_000));

        $this->expectException(RateLimiterException::class);

        try {
            $this->route->log($this->payload(['consentAction' => 'accept_all']), $this->request('203.0.113.7'), Generator::generateSalesChannelContext());
        } finally {
            static::assertSame([], $this->storage->calls);
        }
    }

    public function testRequestsWithoutAClientIpShareOneRateLimit(): void
    {
        // Without a client IP there is no key per client, but the limit must not be skipped
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::COOKIE_CONSENT_LOG, '');

        $response = $this->createRoute($rateLimiter)->log($this->payload(['consentAction' => 'accept_all']), new Request(), Generator::generateSalesChannelContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    private function createRoute(RateLimiter $rateLimiter): CookieConsentLogRoute
    {
        $cookieRoute = static::createStub(AbstractCookieRoute::class);
        $cookieRoute->method('getCookieGroups')
            ->willReturn(new CookieRouteResponse($this->cookieGroups(), 'server-hash', 'language-id'));

        return new CookieConsentLogRoute(
            $cookieRoute,
            $this->storage,
            new MockClock('2026-07-13 12:00:00'),
            $rateLimiter,
            new ArrayAdapter(),
        );
    }

    /**
     * @param array{consentAction: string, acceptedCookies?: list<string>} $payload
     */
    private function log(array $payload): CookieConsentRecord
    {
        $this->route->log($this->payload($payload), new Request(), Generator::generateSalesChannelContext());

        $record = end($this->storage->records);
        static::assertInstanceOf(CookieConsentRecord::class, $record);

        return $record;
    }

    /**
     * @param array{consentAction: string, acceptedCookies?: list<string>} $payload
     */
    private function payload(array $payload): CookieConsentLogPayload
    {
        return new CookieConsentLogPayload(
            consentId: self::CONSENT_ID,
            consentAction: CookieConsentAction::from($payload['consentAction']),
            acceptedCookies: $payload['acceptedCookies'] ?? [],
        );
    }

    private function request(string $clientIp): Request
    {
        return new Request(server: ['REMOTE_ADDR' => $clientIp]);
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

/**
 * @internal
 */
class InMemoryCookieConsentLogStorage extends AbstractCookieConsentLogStorage
{
    /**
     * @var list<CookieConsentRecord>
     */
    public array $records = [];

    /**
     * @var list<CookieConsentConfigSnapshot>
     */
    public array $snapshots = [];

    /**
     * @var list<string>
     */
    public array $calls = [];

    public ?\Throwable $failNextSnapshot = null;

    public function log(CookieConsentRecord $record): void
    {
        $this->calls[] = 'log';
        $this->records[] = $record;
    }

    public function snapshot(CookieConsentConfigSnapshot $snapshot): void
    {
        $this->calls[] = 'snapshot';

        if ($this->failNextSnapshot !== null) {
            $failure = $this->failNextSnapshot;
            $this->failNextSnapshot = null;

            throw $failure;
        }

        $this->snapshots[] = $snapshot;
    }

    public function cleanup(\DateTimeInterface $before): void
    {
        $this->calls[] = 'cleanup';
    }

    public function iterate(\DateTimeInterface $from, \DateTimeInterface $to, ?string $salesChannelId = null): iterable
    {
        return $this->records;
    }
}
