<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\ConsentLog\AbstractCookieConsentLogStorage;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentAction;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentConfigSnapshot;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentDecision;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use Shopware\Core\Content\Cookie\CookieException;
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
use Shopware\Core\Test\Generator;
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

        $this->route->log($this->request(['consentAction' => 'accept_all']), $salesChannelContext);

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
            array_map(static fn (CookieGroup $group) => $group->getTechnicalName(), $snapshot->cookieGroups),
        );
        static::assertSame('2026-07-13 12:00:00', $snapshot->createdAt->format('Y-m-d H:i:s'));
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
        $response = $this->route->log($this->request(['consentAction' => 'accept_all']), Generator::generateSalesChannelContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertCount(1, $this->storage->records);
    }

    /**
     * @param array<string, mixed>|string $body
     */
    #[DataProvider('invalidPayloadProvider')]
    public function testLogRejectsInvalidPayloads(array|string $body, string $reason): void
    {
        $this->expectExceptionObject(CookieException::invalidConsentLogPayload($reason));

        try {
            $this->route->log(
                new Request(content: \is_string($body) ? $body : (string) json_encode($body)),
                Generator::generateSalesChannelContext(),
            );
        } finally {
            static::assertSame([], $this->storage->records);
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>|string, string}>
     */
    public static function invalidPayloadProvider(): iterable
    {
        $consentIdReason = 'consentId must be a string of 1 to 64 letters, digits, dashes or underscores';
        $actionReason = 'consentAction must be one of: accept_all, accept_required, accept_selected';

        yield 'no json' => ['no-json{', 'body must be valid JSON'];
        yield 'no object' => ['"a-string"', 'body must be a JSON object'];
        yield 'missing consent id' => [['consentAction' => 'accept_all'], $consentIdReason];
        yield 'empty consent id' => [['consentId' => '', 'consentAction' => 'accept_all'], $consentIdReason];
        yield 'consent id with forbidden characters' => [['consentId' => 'a b/c', 'consentAction' => 'accept_all'], $consentIdReason];
        yield 'consent id too long' => [['consentId' => str_repeat('a', 65), 'consentAction' => 'accept_all'], $consentIdReason];
        yield 'consent id no string' => [['consentId' => 42, 'consentAction' => 'accept_all'], $consentIdReason];
        yield 'missing action' => [['consentId' => self::CONSENT_ID], $actionReason];
        yield 'unknown action' => [['consentId' => self::CONSENT_ID, 'consentAction' => 'reject_all'], $actionReason];
        yield 'accepted cookies no list' => [
            ['consentId' => self::CONSENT_ID, 'consentAction' => 'accept_selected', 'acceptedCookies' => ['key' => 'value']],
            'acceptedCookies must be a list with at most 500 entries',
        ];
        yield 'accepted cookies with non strings' => [
            ['consentId' => self::CONSENT_ID, 'consentAction' => 'accept_selected', 'acceptedCookies' => ['lorem', 42]],
            'acceptedCookies must contain non-empty strings',
        ];
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

        $request = $this->request(['consentAction' => 'accept_all'], '203.0.113.7');

        $this->createRoute($rateLimiter)->log($request, Generator::generateSalesChannelContext());
    }

    public function testAnExceededRateLimitStoresNothing(): void
    {
        $this->rateLimiter->method('ensureAccepted')
            ->willThrowException(RateLimiterException::limitExceeded(2_000_000_000));

        $this->expectException(RateLimiterException::class);

        try {
            $this->route->log($this->request(['consentAction' => 'accept_all'], '203.0.113.7'), Generator::generateSalesChannelContext());
        } finally {
            static::assertSame([], $this->storage->calls);
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
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->never())->method('ensureAccepted');

        $response = $this->createRoute($rateLimiter)->log($this->request(['consentAction' => 'accept_all']), Generator::generateSalesChannelContext());

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
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function log(array $payload): CookieConsentRecord
    {
        $this->route->log($this->request($payload), Generator::generateSalesChannelContext());

        $record = end($this->storage->records);
        static::assertInstanceOf(CookieConsentRecord::class, $record);

        return $record;
    }

    /**
     * @param array<string, mixed> $payload without consentId, which is added
     */
    private function request(array $payload, ?string $clientIp = null): Request
    {
        return new Request(
            server: $clientIp === null ? [] : ['REMOTE_ADDR' => $clientIp],
            content: (string) json_encode(['consentId' => self::CONSENT_ID, ...$payload]),
        );
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

    public function log(CookieConsentRecord $record): void
    {
        $this->calls[] = 'log';
        $this->records[] = $record;
    }

    public function snapshot(CookieConsentConfigSnapshot $snapshot): void
    {
        $this->calls[] = 'snapshot';
        $this->snapshots[] = $snapshot;
    }

    public function cleanup(\DateTimeImmutable $before): void
    {
        $this->calls[] = 'cleanup';
    }

    public function iterate(\DateTimeImmutable $from, \DateTimeImmutable $to, ?string $salesChannelId = null): iterable
    {
        return $this->records;
    }
}
