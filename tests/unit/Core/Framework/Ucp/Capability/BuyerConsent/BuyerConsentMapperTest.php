<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability\BuyerConsent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Capability\BuyerConsent\BuyerConsentMapper;
use Shopware\Core\Framework\Ucp\Capability\BuyerConsent\ConsentStore;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * The buyer-consent mapper is the single read/write helper for the
 * `buyer.consent` block of the platform request. It is responsible for the
 * audit-trail (granted_at / denied_at) and for whitelisting consent keys —
 * any drift here means platform-supplied consent decisions go missing or
 * unknown fields leak through to persistence. Both are spec violations of
 * `buyer-consent.md`.
 *
 * @internal
 */
#[CoversClass(BuyerConsentMapper::class)]
class BuyerConsentMapperTest extends TestCase
{
    public function testReturnsNullSnapshotWhenStoreIsEmptyAndNoIncomingConsent(): void
    {
        $store = $this->createMock(ConsentStore::class);
        $store->method('load')->with('ck_1')->willReturn(null);
        $store->expects($this->never())->method('save');

        $mapper = new BuyerConsentMapper($store);
        $snapshot = $mapper->applyAndReturn(null, $this->context('sc-1'), 'ck_1');

        static::assertNull($snapshot);
    }

    public function testReplaysStoredSnapshotWithoutWritingWhenNoIncoming(): void
    {
        $existing = ['terms_of_service' => ['granted' => true, 'granted_at' => '2026-01-01T00:00:00Z']];
        $store = $this->createMock(ConsentStore::class);
        $store->method('load')->with('ck_1')->willReturn($existing);
        $store->expects($this->never())->method('save');

        $snapshot = (new BuyerConsentMapper($store))->applyAndReturn(null, $this->context('sc-1'), 'ck_1');

        static::assertSame($existing, $snapshot);
    }

    public function testPersistsAndReturnsMergedSnapshotForIncomingConsent(): void
    {
        $store = $this->createMock(ConsentStore::class);
        $store->method('load')->willReturn(null);
        $store->expects($this->once())
            ->method('save')
            ->with('ck_1', 'sc-1', static::callback(static fn (array $s): bool => (
                isset($s['terms_of_service']['granted'])
                && $s['terms_of_service']['granted'] === true
                && isset($s['terms_of_service']['granted_at'])
            )));

        $incoming = [
            'terms_of_service' => ['granted' => true, 'granted_at' => '2026-05-20T12:00:00Z'],
        ];

        $snapshot = (new BuyerConsentMapper($store))->applyAndReturn($incoming, $this->context('sc-1'), 'ck_1');

        static::assertIsArray($snapshot);
        static::assertTrue($snapshot['terms_of_service']['granted']);
        static::assertSame('2026-05-20T12:00:00Z', $snapshot['terms_of_service']['granted_at']);
    }

    public function testDropsUnsupportedConsentKeysSilently(): void
    {
        $store = $this->createMock(ConsentStore::class);
        $store->method('load')->willReturn(null);

        $incoming = [
            'terms_of_service' => ['granted' => true],
            'made_up_consent_field' => ['granted' => true],
        ];

        $snapshot = (new BuyerConsentMapper($store))->applyAndReturn($incoming, $this->context('sc-1'), 'ck_1');

        static::assertIsArray($snapshot);
        static::assertArrayHasKey('terms_of_service', $snapshot);
        static::assertArrayNotHasKey('made_up_consent_field', $snapshot);
    }

    public function testDenialEmitsDeniedAtAuditTrail(): void
    {
        $store = $this->createMock(ConsentStore::class);
        $store->method('load')->willReturn(null);

        $incoming = [
            'marketing_email' => ['granted' => false, 'denied_at' => '2026-05-20T12:00:00Z'],
        ];

        $snapshot = (new BuyerConsentMapper($store))->applyAndReturn($incoming, $this->context('sc-1'), 'ck_1');

        static::assertIsArray($snapshot);
        static::assertFalse($snapshot['marketing_email']['granted']);
        static::assertSame('2026-05-20T12:00:00Z', $snapshot['marketing_email']['denied_at']);
    }

    public function testDenialDefaultsToCurrentTimestampWhenDeniedAtIsMissing(): void
    {
        $store = $this->createMock(ConsentStore::class);
        $store->method('load')->willReturn(null);

        $snapshot = (new BuyerConsentMapper($store))->applyAndReturn(
            ['marketing_email' => ['granted' => false]],
            $this->context('sc-1'),
            'ck_1'
        );

        static::assertIsArray($snapshot);
        static::assertIsString($snapshot['marketing_email']['denied_at']);
        // ISO-8601 sanity check — at least a 'T' between date and time.
        static::assertMatchesRegularExpression('/T/', $snapshot['marketing_email']['denied_at']);
    }

    public function testGrantedConsentPassesThroughOptionalFields(): void
    {
        $store = $this->createMock(ConsentStore::class);
        $store->method('load')->willReturn(null);

        $incoming = [
            'data_sharing_agent' => [
                'granted' => true,
                'granted_at' => '2026-05-20T12:00:00Z',
                'scope' => ['catalog', 'cart', 123, null],
                'jurisdiction' => 'EU',
                'basis' => 'consent',
                'policy_version' => 'v2',
            ],
        ];

        $snapshot = (new BuyerConsentMapper($store))->applyAndReturn($incoming, $this->context('sc-1'), 'ck_1');

        static::assertIsArray($snapshot);
        // Scope is filtered to strings only.
        static::assertSame(['catalog', 'cart'], $snapshot['data_sharing_agent']['scope']);
        static::assertSame('EU', $snapshot['data_sharing_agent']['jurisdiction']);
        static::assertSame('consent', $snapshot['data_sharing_agent']['basis']);
        static::assertSame('v2', $snapshot['data_sharing_agent']['policy_version']);
    }

    public function testIgnoresNonArrayDecisionsForKnownKeys(): void
    {
        $store = $this->createMock(ConsentStore::class);
        $store->method('load')->willReturn(null);
        $store->expects($this->once())
            ->method('save')
            ->with('ck_1', 'sc-1', static::callback(static fn (array $s): bool => $s === []));

        $snapshot = (new BuyerConsentMapper($store))->applyAndReturn(
            ['terms_of_service' => 'not-an-array'],
            $this->context('sc-1'),
            'ck_1'
        );

        // mergeConsent skips non-array decisions silently — the empty merged
        // snapshot is still persisted (idempotent), but contains no consent.
        static::assertSame([], $snapshot);
    }

    public function testMergesIncomingOntoExistingSnapshot(): void
    {
        $store = $this->createMock(ConsentStore::class);
        $store->method('load')->willReturn([
            'terms_of_service' => ['granted' => true, 'granted_at' => '2026-01-01T00:00:00Z'],
        ]);
        $store->expects($this->once())->method('save');

        $snapshot = (new BuyerConsentMapper($store))->applyAndReturn(
            ['marketing_email' => ['granted' => true, 'granted_at' => '2026-05-20T12:00:00Z']],
            $this->context('sc-1'),
            'ck_1'
        );

        // Both keys present after merge — previous terms preserved.
        static::assertIsArray($snapshot);
        static::assertArrayHasKey('terms_of_service', $snapshot);
        static::assertArrayHasKey('marketing_email', $snapshot);
    }

    public function testAsDataBagWrapsConsentForSubsequentSwSchedules(): void
    {
        $bag = BuyerConsentMapper::asDataBag(['terms_of_service' => ['granted' => true]]);

        // The return type narrows statically to RequestDataBag — we just
        // exercise the value to keep the test meaningful.
        static::assertTrue($bag->all()['consent']['terms_of_service']['granted']);
    }

    public function testAsDataBagWithNullConsentReturnsEmptyConsentBag(): void
    {
        $bag = BuyerConsentMapper::asDataBag(null);

        static::assertSame([], $bag->all()['consent']);
    }

    private function context(string $salesChannelId): SalesChannelContext
    {
        $ctx = $this->createMock(SalesChannelContext::class);
        $ctx->method('getSalesChannelId')->willReturn($salesChannelId);

        return $ctx;
    }
}
