<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Health;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Health\WebhookDispatchDecision;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class EndpointHealthStateMachineMatrixTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const URL = 'https://endpoint.example.com/hook';

    private const DEGRADED_THRESHOLD = 5;
    private const NON_TRANSIENT_THRESHOLD = 3;
    private const COOLDOWN_TIER_0 = 300;
    private const COOLDOWN_TIER_2 = 1200;
    private const COOLDOWN_TOP_TIER = 14400;
    private const TOP_TIER_INDEX = 5;

    private IdsCollection $ids;

    private Connection $connection;

    private WebhookHealthService $service;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->service = static::getContainer()->get(WebhookHealthService::class);
    }

    public function testOnlyTheFailingWebhookForASharedEndpointDegradesAtTheFirstAttemptThreshold(): void
    {
        $this->seedWebhook('webhook-a', active: true, errorCount: 0);
        $this->seedWebhook('webhook-b', active: true, errorCount: 0);
        $this->seedHealth('webhook-a', EndpointState::Healthy, ctf: self::DEGRADED_THRESHOLD - 1);
        $this->seedHealth('webhook-b', EndpointState::Healthy, ctf: self::DEGRADED_THRESHOLD - 1);
        $this->createDelivery('a-queued', 'webhook-a', WebhookEventLogDefinition::STATUS_QUEUED);
        $this->createDelivery('a-pending', 'webhook-a', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->createDelivery('b-queued', 'webhook-b', WebhookEventLogDefinition::STATUS_QUEUED);

        $retryResult = $this->service->recordFailure(
            $this->ids->get('webhook-a'),
            ErrorClassification::TransientNetwork,
            2,
        );

        static::assertSame(EndpointState::Healthy, $retryResult);
        static::assertSame(self::DEGRADED_THRESHOLD - 1, $this->fetchCtf('webhook-a'));
        $this->assertDeliveryStatus('a-queued', WebhookEventLogDefinition::STATUS_QUEUED);

        $result = $this->service->recordFailure(
            $this->ids->get('webhook-a'),
            ErrorClassification::TransientNetwork,
            1,
        );

        static::assertSame(EndpointState::Degraded, $result);
        $this->assertState('webhook-a', EndpointState::Degraded);
        static::assertSame(self::DEGRADED_THRESHOLD, $this->fetchCtf('webhook-a'));
        $this->assertHealthTimestampAbout('webhook-a', 'cooldown_until', self::COOLDOWN_TIER_0);
        $this->assertDeliveryStatus('a-queued', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->assertDeliveryStatus('a-pending', WebhookEventLogDefinition::STATUS_PAUSED);
        static::assertTrue($this->fetchActive('webhook-a'));
        static::assertSame(self::DEGRADED_THRESHOLD, $this->fetchErrorCount('webhook-a'));

        $this->assertState('webhook-b', EndpointState::Healthy);
        static::assertSame(self::DEGRADED_THRESHOLD - 1, $this->fetchCtf('webhook-b'));
        $this->assertDeliveryStatus('b-queued', WebhookEventLogDefinition::STATUS_QUEUED);
        static::assertTrue($this->fetchActive('webhook-b'));
        static::assertSame(0, $this->fetchErrorCount('webhook-b'));
    }

    public function testSuccessfulTrialRecoversTheWebhookAndResumesItsBacklog(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 5);
        $this->seedHealth(
            'wh',
            EndpointState::Degraded,
            ctf: 5,
            cnf: 1,
            cycle: 2,
            cooldownUntil: (new \DateTimeImmutable('+1 hour'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        );
        $this->createDelivery('evt-held', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->service->recordSuccess($this->ids->get('wh'));

        $this->assertState('wh', EndpointState::Healthy);
        static::assertSame(0, $this->fetchCtf('wh'));
        static::assertSame(0, $this->fetchCnf('wh'));
        static::assertSame(0, $this->fetchCycle('wh'));
        static::assertNull($this->fetchHealthColumn('wh', 'cooldown_until'));
        $this->assertDeliveryStatus('evt-held', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        static::assertTrue($this->fetchActive('wh'));
        static::assertSame(0, $this->fetchErrorCount('wh'));
    }

    public function testOnlyFailuresAfterTheCooldownAdvanceTheDegradedLadder(): void
    {
        $futureCooldown = (new \DateTimeImmutable('+1 hour'))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->seedWebhook('trial', active: true, errorCount: 5);
        $this->seedWebhook('straggler', active: true, errorCount: 5);
        $this->seedHealth('trial', EndpointState::Degraded, cycle: 1, cooldownUntil: self::past());
        $this->seedHealth('straggler', EndpointState::Degraded, cycle: 1, cooldownUntil: $futureCooldown);

        $this->service->recordFailure($this->ids->get('trial'), ErrorClassification::TransientServer, 1);
        $this->service->recordFailure($this->ids->get('straggler'), ErrorClassification::TransientServer, 1);

        static::assertSame(2, $this->fetchCycle('trial'));
        $this->assertHealthTimestampAbout('trial', 'cooldown_until', self::COOLDOWN_TIER_2);
        static::assertSame(1, $this->fetchCycle('straggler'));
        static::assertSame($futureCooldown, $this->fetchHealthColumn('straggler', 'cooldown_until'));
    }

    /**
     * @return iterable<string, array{EndpointState, int, int, int, ?bool, ErrorClassification, int, EndpointState, int, int, int, bool, int}>
     */
    public static function recordFailureMatrixProvider(): iterable
    {
        // Seed: state, transient streak, auth streak, ladder tier, cooldown elapsed.
        // Result: state, transient streak, auth streak, ladder tier, active, error_count.
        yield 'HEALTHY ignores transient retries' => [EndpointState::Healthy, 4, 0, 0, null, ErrorClassification::TransientServer, 2, EndpointState::Healthy, 4, 0, 0, true, 0];
        yield 'HEALTHY mirrors the larger streak when auth failures suspend it' => [EndpointState::Healthy, 4, 2, 0, null, ErrorClassification::NonTransientAuth, 1, EndpointState::Suspended, 4, 3, 0, false, 4];
        yield 'HEALTHY ignores payload failures' => [EndpointState::Healthy, 0, 0, 0, null, ErrorClassification::NonTransientPayload, 1, EndpointState::Healthy, 0, 0, 0, true, 0];
        yield 'DEGRADED counts retry results after the cooldown' => [EndpointState::Degraded, 5, 0, 0, true, ErrorClassification::TransientServer, 2, EndpointState::Degraded, 5, 0, 1, true, 5];
        yield 'DEGRADED counts auth failures during the cooldown without advancing' => [EndpointState::Degraded, 5, 0, 0, false, ErrorClassification::NonTransientAuth, 1, EndpointState::Degraded, 5, 1, 0, true, 5];
        yield 'DEGRADED counts auth failures and advances after the cooldown' => [EndpointState::Degraded, 5, 0, 0, true, ErrorClassification::NonTransientAuth, 1, EndpointState::Degraded, 5, 1, 1, true, 5];
        yield 'SUSPENDED ignores transient results during the cooldown' => [EndpointState::Suspended, 5, 3, 0, false, ErrorClassification::TransientServer, 1, EndpointState::Suspended, 5, 3, 0, false, 5];
        yield 'SUSPENDED advances after a failed transient trial' => [EndpointState::Suspended, 5, 3, 0, true, ErrorClassification::TransientServer, 1, EndpointState::Suspended, 5, 3, 1, false, 5];
        yield 'SUSPENDED counts auth failures and advances after the cooldown' => [EndpointState::Suspended, 0, 3, 0, true, ErrorClassification::NonTransientAuth, 1, EndpointState::Suspended, 0, 4, 1, false, 4];
        yield 'SUSPENDED advances after an endpoint-gone result without changing the streak' => [EndpointState::Suspended, 0, 3, 0, true, ErrorClassification::NonTransientEndpoint, 1, EndpointState::Suspended, 0, 3, 1, false, 3];
        yield 'DISABLED absorbs an auth failure' => [EndpointState::Disabled, 2, 3, 0, null, ErrorClassification::NonTransientAuth, 1, EndpointState::Disabled, 2, 3, 0, false, 3];
    }

    #[DataProvider('recordFailureMatrixProvider')]
    public function testRecordFailureTransition(
        EndpointState $from,
        int $seedCtf,
        int $seedCnf,
        int $seedCycle,
        ?bool $cooldownElapsed,
        ErrorClassification $classification,
        int $attempt,
        EndpointState $expected,
        int $expectedCtf,
        int $expectedCnf,
        int $expectedCycle,
        bool $expectedActive,
        int $expectedErrorCount
    ): void {
        $this->seedWebhook(
            'wh',
            active: $from === EndpointState::Healthy || $from === EndpointState::Degraded,
            errorCount: $this->mirrorErrorCount($from, $seedCtf, $seedCnf),
        );
        $this->seedHealth(
            'wh',
            $from,
            ctf: $seedCtf,
            cnf: $seedCnf,
            cycle: $seedCycle,
            cooldownUntil: $cooldownElapsed === null ? null : ($cooldownElapsed ? self::past() : self::future()),
            suspendedSince: $from === EndpointState::Suspended ? self::past() : null,
        );

        $result = $this->service->recordFailure($this->ids->get('wh'), $classification, $attempt);

        static::assertSame($expected, $result, 'recordFailure must return the resulting endpoint state');
        $this->assertState('wh', $expected);
        static::assertSame($expectedCtf, $this->fetchCtf('wh'), 'consecutive_transient_failures');
        static::assertSame($expectedCnf, $this->fetchCnf('wh'), 'consecutive_non_transient_failures');
        static::assertSame($expectedCycle, $this->fetchCycle('wh'), 'degraded_cycle_count');
        static::assertSame($expectedActive, $this->fetchActive('wh'), 'BC mirror: active');
        static::assertSame($expectedErrorCount, $this->fetchErrorCount('wh'), 'BC mirror: error_count');
    }

    public function testAuthStreakSuspendsOnlyAtThreeConsecutiveFailuresWithoutASuccessInBetween(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);

        $this->recordAuthFailures('wh', 2);
        $this->assertState('wh', EndpointState::Healthy);
        static::assertSame(2, $this->fetchCnf('wh'));

        $this->service->recordSuccess($this->ids->get('wh'));
        static::assertSame(0, $this->fetchCnf('wh'), 'any 2xx resets the auth streak');

        $this->recordAuthFailures('wh', 2);
        $this->assertState('wh', EndpointState::Healthy, 'the pre-success failures no longer count');
        static::assertSame(2, $this->fetchCnf('wh'));

        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);
        $this->assertState('wh', EndpointState::Healthy);
        static::assertSame(2, $this->fetchCnf('wh'), 'a transient failure leaves the auth streak untouched');

        $this->createDelivery('evt-queued', 'wh', WebhookEventLogDefinition::STATUS_QUEUED);
        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientAuth, 1);

        static::assertSame(EndpointState::Suspended, $result, 'the third post-reset auth failure suspends');
        static::assertSame(3, $this->fetchCnf('wh'));
        static::assertSame(0, $this->fetchCycle('wh'), 'a direct trip enters the ladder at tier 0');
        $this->assertHealthTimestampAbout('wh', 'suspended_since', 0);
        $this->assertHealthTimestampAbout('wh', 'cooldown_until', self::COOLDOWN_TIER_0);
        $this->assertDeliveryStatus('evt-queued', WebhookEventLogDefinition::STATUS_PAUSED);
    }

    public function testGoneSuspendsImmediatelyWithTierZeroCooldownAndHoldsTheBacklog(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);
        $this->seedHealth('wh', EndpointState::Healthy);
        $this->createDelivery('evt-queued', 'wh', WebhookEventLogDefinition::STATUS_QUEUED);

        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientEndpoint, 1);

        static::assertSame(EndpointState::Suspended, $result);
        static::assertSame(0, $this->fetchCnf('wh'), '410 bypasses the auth streak, leaving it untouched');
        static::assertSame(0, $this->fetchCycle('wh'), 'a direct trip enters the ladder at tier 0');
        $this->assertHealthTimestampAbout('wh', 'suspended_since', 0);
        $this->assertHealthTimestampAbout('wh', 'cooldown_until', self::COOLDOWN_TIER_0);
        $this->assertDeliveryStatus('evt-queued', WebhookEventLogDefinition::STATUS_PAUSED);
    }

    public function testTrialSuccessOnSuspendedDeEscalatesOneStateKeepingSuspendedSinceAndTheHeldBacklog(): void
    {
        $since = self::past();
        $this->seedWebhook('wh', active: false, errorCount: 5);
        $this->seedHealth('wh', EndpointState::Suspended, ctf: 5, cnf: 3, cycle: 4, cooldownUntil: self::past(), suspendedSince: $since);
        $this->createDelivery('evt-held', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->service->recordSuccess($this->ids->get('wh'));

        $this->assertState('wh', EndpointState::Degraded);
        static::assertSame(0, $this->fetchCycle('wh'));
        static::assertSame(0, $this->fetchCtf('wh'));
        static::assertSame(0, $this->fetchCnf('wh'));
        $this->assertHealthTimestampAbout('wh', 'cooldown_until', self::COOLDOWN_TIER_0);
        static::assertSame($since, $this->fetchHealthColumn('wh', 'suspended_since'), 'suspended_since survives the de-escalation');
        $this->assertDeliveryStatus('evt-held', WebhookEventLogDefinition::STATUS_PAUSED);
        static::assertTrue($this->fetchActive('wh'), 'DEGRADED mirrors active = 1');
        static::assertSame(0, $this->fetchErrorCount('wh'), 'both streaks reset, so the mirrored count is 0');
    }

    public function testHealthyRecoveryClearsTheSuspensionTimestamp(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 5);
        $this->seedHealth('wh', EndpointState::Degraded, ctf: 5, cnf: 1, cycle: 2, cooldownUntil: self::future(), suspendedSince: self::past());

        $this->service->recordSuccess($this->ids->get('wh'));

        static::assertNull($this->fetchHealthColumn('wh', 'suspended_since'));
    }

    public function testRecordSuccessReconcilesAStaleLegacyErrorCountWithoutAHealthRow(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 7);

        $this->service->recordSuccess($this->ids->get('wh'));

        static::assertSame(0, $this->fetchErrorCount('wh'), 'a success clears a stale legacy error_count even with no health row');
    }

    public function testRecordSuccessOnADisabledWebhookChangesNothing(): void
    {
        $this->seedWebhook('wh', active: false, errorCount: 3);
        $this->seedHealth('wh', EndpointState::Disabled, ctf: 2, cnf: 3);

        $this->service->recordSuccess($this->ids->get('wh'));

        $this->assertState('wh', EndpointState::Disabled);
        static::assertSame(2, $this->fetchCtf('wh'));
        static::assertSame(3, $this->fetchCnf('wh'));
        static::assertFalse($this->fetchActive('wh'), 'DISABLED absorbs — a stray 2xx must not reactivate');
        static::assertSame(3, $this->fetchErrorCount('wh'));
    }

    public function testSuspendedSinceKeepsTheOriginalTimestampAcrossAFlapAndClearsOnlyAtHealthy(): void
    {
        $original = self::past();
        $this->seedWebhook('wh', active: false, errorCount: 3);
        $this->seedHealth('wh', EndpointState::Suspended, cnf: 3, cooldownUntil: self::future(), suspendedSince: $original);

        $this->service->recordSuccess($this->ids->get('wh'));
        $this->assertState('wh', EndpointState::Degraded);
        static::assertSame($original, $this->fetchHealthColumn('wh', 'suspended_since'));

        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientEndpoint, 1);
        $this->assertState('wh', EndpointState::Suspended);
        static::assertSame($original, $this->fetchHealthColumn('wh', 'suspended_since'), 'a flap keeps the original suspension timestamp');

        $this->service->recordSuccess($this->ids->get('wh'));
        $this->service->recordSuccess($this->ids->get('wh'));
        $this->assertState('wh', EndpointState::Healthy);
        static::assertNull($this->fetchHealthColumn('wh', 'suspended_since'), 'only reaching HEALTHY clears the suspension clock');
    }

    public function testExhaustingTheCooldownScheduleSuspendsAtTheTopTier(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 5);
        $this->seedHealth('wh', EndpointState::Degraded, ctf: 5, cycle: self::TOP_TIER_INDEX, cooldownUntil: self::past());

        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);

        static::assertSame(EndpointState::Suspended, $result, 'the DEGRADED budget is the schedule length');
        static::assertSame(self::TOP_TIER_INDEX, $this->fetchCycle('wh'), 'the ladder stays at the top tier');
        $this->assertHealthTimestampAbout('wh', 'cooldown_until', self::COOLDOWN_TOP_TIER);
        $this->assertHealthTimestampAbout('wh', 'suspended_since', 0);
    }

    public function testSuspensionFromTheAuthStreakHoldsAgainstARacingTransientResult(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);
        $this->seedHealth('wh', EndpointState::Healthy, cnf: self::NON_TRANSIENT_THRESHOLD - 1);

        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientAuth, 1);
        static::assertSame(EndpointState::Suspended, $result);

        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);

        static::assertSame(EndpointState::Suspended, $result);
        $this->assertState('wh', EndpointState::Suspended);
        static::assertSame(self::NON_TRANSIENT_THRESHOLD, $this->fetchCnf('wh'));
        static::assertSame(0, $this->fetchCycle('wh'), 'the late transient neither re-transitions nor climbs the ladder');
        static::assertFalse($this->fetchActive('wh'));
    }

    public function testSuccessWinsOverAPriorTransientFailure(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);
        $this->seedHealth('wh', EndpointState::Healthy);

        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);
        $this->service->recordSuccess($this->ids->get('wh'));

        $this->assertState('wh', EndpointState::Healthy);
        static::assertSame(0, $this->fetchCtf('wh'));
    }

    public function testMissingHealthRowGatesAsDeliverWithoutWritingARow(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);

        static::assertSame(WebhookDispatchDecision::Deliver, $this->service->gateFor($this->ids->get('wh')));
        static::assertFalse(
            (bool) $this->connection->fetchOne('SELECT 1 FROM webhook_health WHERE webhook_id = :id', ['id' => $this->ids->getBytes('wh')]),
            'gateFor is a read — only a state-changing failure upserts the health row'
        );
    }

    public function testSuspendedGateAdmitsExactlyOneNaturalTrialOnceTheCooldownElapsesWithNothingHeld(): void
    {
        $this->seedWebhook('wh', active: false, errorCount: 3);
        $this->seedHealth('wh', EndpointState::Suspended, cnf: 3, cycle: 1, cooldownUntil: self::past(), suspendedSince: self::past());

        static::assertSame(
            WebhookDispatchDecision::Deliver,
            $this->service->gateFor($this->ids->get('wh')),
            'an elapsed cooldown with nothing held and nothing in flight must admit the natural event as the trial'
        );
        static::assertSame(2, $this->fetchCycle('wh'), 'admission advances the ladder one tier so its own result lands as a straggler');
        static::assertSame(
            WebhookDispatchDecision::Skip,
            $this->service->gateFor($this->ids->get('wh')),
            'exactly one Deliver — the admission re-armed the cooldown, so the rest of the burst sheds'
        );
    }

    public function testFirstTransientFailureOnAWebhookWithoutHealthRowInsertsHealthyRow(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);

        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);

        static::assertSame(EndpointState::Healthy, $result);
        $this->assertState('wh', EndpointState::Healthy);
        static::assertSame(1, $this->fetchCtf('wh'), 'the first failure seeds consecutive_transient_failures = 1');
        static::assertSame(0, $this->fetchErrorCount('wh'), 'HEALTHY still mirrors error_count = 0');
    }

    public function testFirstAuthFailureOnAWebhookWithoutHealthRowInsertsTheStreakRow(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);

        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientAuth, 1);

        static::assertSame(EndpointState::Healthy, $result, 'one auth failure is below the streak threshold');
        $this->assertState('wh', EndpointState::Healthy);
        static::assertSame(1, $this->fetchCnf('wh'), 'the first failure seeds consecutive_non_transient_failures = 1');
        static::assertTrue($this->fetchActive('wh'));
        static::assertSame(0, $this->fetchErrorCount('wh'));
    }

    public function testGoneFailureOnAWebhookWithoutHealthRowInsertsASuspendedRow(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);

        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientEndpoint, 1);

        static::assertSame(EndpointState::Suspended, $result, '410 suspends even on the first contact');
        $this->assertState('wh', EndpointState::Suspended);
        $this->assertHealthTimestampAbout('wh', 'suspended_since', 0);
        $this->assertHealthTimestampAbout('wh', 'cooldown_until', self::COOLDOWN_TIER_0);
        static::assertFalse($this->fetchActive('wh'));
    }

    public function testAuthSuspensionOfOneWebhookLeavesItsEventUrlSiblingUntouched(): void
    {
        $this->seedWebhook('broken', active: true, errorCount: 0);
        $this->seedWebhook('sibling', active: true, errorCount: 0);

        $this->recordAuthFailures('broken', self::NON_TRANSIENT_THRESHOLD);

        $this->assertState('broken', EndpointState::Suspended);
        static::assertFalse($this->fetchActive('broken'));

        static::assertFalse(
            (bool) $this->connection->fetchOne('SELECT 1 FROM webhook_health WHERE webhook_id = :id', ['id' => $this->ids->getBytes('sibling')]),
            'a sibling that never failed must not get a health row from its neighbour failing'
        );
        static::assertTrue($this->fetchActive('sibling'), 'the sibling stays active — no cross-webhook blast radius');
        static::assertSame(0, $this->fetchErrorCount('sibling'));
    }

    private function recordAuthFailures(string $key, int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $this->service->recordFailure($this->ids->get($key), ErrorClassification::NonTransientAuth, 1);
        }
    }

    private function mirrorErrorCount(EndpointState $state, int $ctf, int $cnf): int
    {
        return $state === EndpointState::Healthy ? 0 : max($ctf, $cnf);
    }

    private static function past(): string
    {
        return (new \DateTimeImmutable('-1 hour'))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }

    private static function future(): string
    {
        return (new \DateTimeImmutable('+1 hour'))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }

    private function seedWebhook(string $key, bool $active, int $errorCount): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($key),
            'name' => $key,
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => self::URL,
            'active' => (int) $active,
            'error_count' => $errorCount,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function seedHealth(
        string $key,
        EndpointState $state,
        int $ctf = 0,
        int $cnf = 0,
        int $cycle = 0,
        ?string $cooldownUntil = null,
        ?string $suspendedSince = null,
    ): void {
        $this->connection->insert('webhook_health', [
            'webhook_id' => $this->ids->getBytes($key),
            'endpoint_state' => $state->value,
            'consecutive_transient_failures' => $ctf,
            'consecutive_non_transient_failures' => $cnf,
            'degraded_cycle_count' => $cycle,
            'cooldown_until' => $cooldownUntil,
            'suspended_since' => $suspendedSince,
            'disabled_since' => $state === EndpointState::Disabled ? self::past() : null,
            'disabled_origin' => $state === EndpointState::Disabled ? DisabledOrigin::Escalation->value : null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createDelivery(string $eventKey, string $webhookKey, string $deliveryStatus): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('webhook_event_log', [
            'id' => $this->ids->getBytes($eventKey),
            'delivery_status' => $deliveryStatus,
            'webhook_name' => 'test-hook',
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => self::URL,
            'created_at' => $now,
        ]);

        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $this->ids->getBytes($eventKey),
            'webhook_id' => $this->ids->getBytes($webhookKey),
            'partition_key' => Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128'),
            'delivery_status' => $deliveryStatus,
            'execution_count' => 0,
            'created_at' => $now,
        ]);
    }

    private function assertState(string $key, EndpointState $expected, string $message = ''): void
    {
        static::assertSame($expected->value, $this->fetchHealthColumn($key, 'endpoint_state'), $message);
    }

    private function assertHealthTimestampAbout(string $key, string $column, int $secondsFromNow): void
    {
        $value = $this->fetchHealthColumn($key, $column);
        static::assertIsString($value, \sprintf('%s must be set', $column));
        static::assertEqualsWithDelta(
            (new \DateTimeImmutable())->getTimestamp() + $secondsFromNow,
            (new \DateTimeImmutable($value))->getTimestamp(),
            30,
            \sprintf('%s must be about %d seconds from now', $column, $secondsFromNow)
        );
    }

    private function fetchHealthColumn(string $key, string $column): ?string
    {
        $value = $this->connection->fetchOne(
            \sprintf('SELECT %s FROM webhook_health WHERE webhook_id = :id', $column),
            ['id' => $this->ids->getBytes($key)]
        );

        return \is_string($value) ? $value : null;
    }

    private function fetchCtf(string $key): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT consecutive_transient_failures FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
    }

    private function fetchCnf(string $key): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT consecutive_non_transient_failures FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
    }

    private function fetchCycle(string $key): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT degraded_cycle_count FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
    }

    private function fetchActive(string $key): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT active FROM webhook WHERE id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
    }

    private function fetchErrorCount(string $key): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT error_count FROM webhook WHERE id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
    }

    private function assertDeliveryStatus(string $eventKey, string $expectedStatus): void
    {
        $deliveryStatus = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );
        $eventLogStatus = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );

        static::assertSame($expectedStatus, $deliveryStatus, 'webhook_delivery status');
        static::assertSame($expectedStatus, $eventLogStatus, 'webhook_event_log status');
    }
}
