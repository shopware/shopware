<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Health;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
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
 * The flag-on circuit-breaker state machine: every classifier input from every state, asserted on the
 * `webhook_health` row AND the legacy `webhook.active`/`error_count` BC mirror (ADR §"Error classification",
 * §"Half-open recovery", §"SUSPENDED"). The matrix locks the contract: the transient threshold (5, first
 * attempts only) trips HEALTHY → DEGRADED; the auth streak (3 deliveries, reset by any 2xx, untouched by
 * transient failures) and a 410 trip → SUSPENDED; a 2xx climbs exactly one state; only a trial result with
 * an elapsed cooldown advances the shared ladder (a straggler during the cooldown is absorbed); both
 * non-HEALTHY trips hold the backlog as `paused` — nothing is dropped. The BC mirror derives `active` from
 * the state and `error_count` from the dominant streak (GREATEST), so an auth-suspended endpoint with zero
 * transient failures still reports a non-zero count. Guarded-transition ordering (threshold crosses once,
 * suspension wins over a racing transient, success wins) is exercised as ordered sequences — true parallel
 * threads aren't reproducible in one process, but the guards make the outcome interleaving-independent,
 * which the sequences pin.
 *
 * @internal
 */
class EndpointHealthStateMachineMatrixTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const URL = 'https://endpoint.example.com/hook';

    /**
     * Mirrors the shopware.webhook.health.* container parameters the service under test is wired with.
     */
    private const DEGRADED_THRESHOLD = 5;
    private const NON_TRANSIENT_THRESHOLD = 3;
    private const COOLDOWN_TIER_0 = 300;
    private const COOLDOWN_TIER_1 = 600;
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

    /**
     * @return iterable<string, array{EndpointState, int, int, int, ?bool, ErrorClassification, int, EndpointState, int, int, int, bool, int}>
     */
    public static function recordFailureMatrixProvider(): iterable
    {
        // from, seed ctf, seed cnf, seed cycle, cooldown elapsed (null = none),
        // classification, attempt
        // => expected state, ctf, cnf, cycle, active, error_count
        yield 'HEALTHY + transient (attempt 1) below threshold counts the streak' => [EndpointState::Healthy, 0, 0, 0, null, ErrorClassification::TransientServer, 1, EndpointState::Healthy, 1, 0, 0, true, 0];
        yield 'HEALTHY + server error (attempt 1) crossing the threshold DEGRADES at tier 0' => [EndpointState::Healthy, 4, 0, 0, null, ErrorClassification::TransientServer, 1, EndpointState::Degraded, 5, 0, 0, true, 5];
        yield 'HEALTHY + network error counts like a server error' => [EndpointState::Healthy, 4, 0, 0, null, ErrorClassification::TransientNetwork, 1, EndpointState::Degraded, 5, 0, 0, true, 5];
        yield 'HEALTHY + rate limit counts like a server error' => [EndpointState::Healthy, 4, 0, 0, null, ErrorClassification::TransientRateLimit, 1, EndpointState::Degraded, 5, 0, 0, true, 5];
        yield 'HEALTHY + unfollowed redirect counts like a server error' => [EndpointState::Healthy, 4, 0, 0, null, ErrorClassification::TransientRedirect, 1, EndpointState::Degraded, 5, 0, 0, true, 5];
        yield 'HEALTHY + transient retry (attempt 2) does NOT count (per-delivery aggregation)' => [EndpointState::Healthy, 4, 0, 0, null, ErrorClassification::TransientServer, 2, EndpointState::Healthy, 4, 0, 0, true, 0];
        yield 'HEALTHY + first auth failure stays HEALTHY (a blip is not a verdict)' => [EndpointState::Healthy, 0, 0, 0, null, ErrorClassification::NonTransientAuth, 1, EndpointState::Healthy, 0, 1, 0, true, 0];
        yield 'HEALTHY + auth streak at the threshold SUSPENDS; zero transient failures still mirror non-zero error_count' => [EndpointState::Healthy, 0, 2, 0, null, ErrorClassification::NonTransientAuth, 1, EndpointState::Suspended, 0, 3, 0, false, 3];
        yield 'HEALTHY + auth crossing with a dominant transient streak mirrors GREATEST' => [EndpointState::Healthy, 4, 2, 0, null, ErrorClassification::NonTransientAuth, 1, EndpointState::Suspended, 4, 3, 0, false, 4];
        yield 'HEALTHY + endpoint gone (410) suspends immediately with the streak untouched' => [EndpointState::Healthy, 0, 0, 0, null, ErrorClassification::NonTransientEndpoint, 1, EndpointState::Suspended, 0, 0, 0, false, 0];
        yield 'HEALTHY + payload failure has no health effect' => [EndpointState::Healthy, 0, 0, 0, null, ErrorClassification::NonTransientPayload, 1, EndpointState::Healthy, 0, 0, 0, true, 0];
        yield 'DEGRADED + transient during a running cooldown is a straggler — the ladder does not advance' => [EndpointState::Degraded, 5, 0, 0, false, ErrorClassification::TransientServer, 1, EndpointState::Degraded, 5, 0, 0, true, 5];
        yield 'DEGRADED + transient after the cooldown elapsed advances one tier' => [EndpointState::Degraded, 5, 0, 0, true, ErrorClassification::TransientServer, 1, EndpointState::Degraded, 5, 0, 1, true, 5];
        yield 'DEGRADED + transient retry (attempt 2) still advances (per-delivery aggregation is HEALTHY-only)' => [EndpointState::Degraded, 5, 0, 0, true, ErrorClassification::TransientServer, 2, EndpointState::Degraded, 5, 0, 1, true, 5];
        yield 'DEGRADED at the schedule end + transient SUSPENDS, ladder staying at the top tier' => [EndpointState::Degraded, 5, 0, 5, true, ErrorClassification::TransientServer, 1, EndpointState::Suspended, 5, 0, 5, false, 5];
        yield 'DEGRADED + auth streak at the threshold suspends at ladder tier 0' => [EndpointState::Degraded, 5, 2, 3, false, ErrorClassification::NonTransientAuth, 1, EndpointState::Suspended, 5, 3, 0, false, 5];
        yield 'DEGRADED + auth below the threshold during a running cooldown only counts the streak (straggler)' => [EndpointState::Degraded, 5, 0, 0, false, ErrorClassification::NonTransientAuth, 1, EndpointState::Degraded, 5, 1, 0, true, 5];
        yield 'DEGRADED + auth below the threshold after the cooldown elapsed counts the streak AND climbs one tier' => [EndpointState::Degraded, 5, 0, 0, true, ErrorClassification::NonTransientAuth, 1, EndpointState::Degraded, 5, 1, 1, true, 5];
        yield 'DEGRADED at the schedule end + auth below the threshold SUSPENDS, ladder staying at the top tier' => [EndpointState::Degraded, 5, 0, 5, true, ErrorClassification::NonTransientAuth, 1, EndpointState::Suspended, 5, 1, 5, false, 5];
        yield 'DEGRADED + endpoint gone (410) suspends immediately at tier 0, streak untouched' => [EndpointState::Degraded, 5, 1, 3, false, ErrorClassification::NonTransientEndpoint, 1, EndpointState::Suspended, 5, 1, 0, false, 5];
        yield 'DEGRADED + payload failure has no health effect' => [EndpointState::Degraded, 5, 0, 2, false, ErrorClassification::NonTransientPayload, 1, EndpointState::Degraded, 5, 0, 2, true, 5];
        yield 'SUSPENDED + transient during a running cooldown is a straggler' => [EndpointState::Suspended, 5, 3, 0, false, ErrorClassification::TransientServer, 1, EndpointState::Suspended, 5, 3, 0, false, 5];
        yield 'SUSPENDED + failed trial (transient, cooldown elapsed) climbs one tier' => [EndpointState::Suspended, 5, 3, 0, true, ErrorClassification::TransientServer, 1, EndpointState::Suspended, 5, 3, 1, false, 5];
        yield 'SUSPENDED at the top tier + failed trial stays capped' => [EndpointState::Suspended, 5, 3, 5, true, ErrorClassification::TransientServer, 1, EndpointState::Suspended, 5, 3, 5, false, 5];
        yield 'SUSPENDED + auth during a running cooldown still counts the streak (once per delivery)' => [EndpointState::Suspended, 0, 3, 0, false, ErrorClassification::NonTransientAuth, 1, EndpointState::Suspended, 0, 4, 0, false, 4];
        yield 'SUSPENDED + auth after the cooldown elapsed counts the streak AND climbs the ladder' => [EndpointState::Suspended, 0, 3, 0, true, ErrorClassification::NonTransientAuth, 1, EndpointState::Suspended, 0, 4, 1, false, 4];
        yield 'SUSPENDED + endpoint gone climbs the ladder with the streak untouched' => [EndpointState::Suspended, 0, 3, 0, true, ErrorClassification::NonTransientEndpoint, 1, EndpointState::Suspended, 0, 3, 1, false, 3];
        yield 'SUSPENDED + payload failure has no health effect' => [EndpointState::Suspended, 5, 3, 2, false, ErrorClassification::NonTransientPayload, 1, EndpointState::Suspended, 5, 3, 2, false, 5];
        yield 'DISABLED absorbs a transient failure' => [EndpointState::Disabled, 2, 3, 0, null, ErrorClassification::TransientServer, 1, EndpointState::Disabled, 2, 3, 0, false, 3];
        yield 'DISABLED absorbs an auth failure' => [EndpointState::Disabled, 2, 3, 0, null, ErrorClassification::NonTransientAuth, 1, EndpointState::Disabled, 2, 3, 0, false, 3];
        yield 'DISABLED absorbs an endpoint-gone failure' => [EndpointState::Disabled, 2, 3, 0, null, ErrorClassification::NonTransientEndpoint, 1, EndpointState::Disabled, 2, 3, 0, false, 3];
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

    public function testCrossingTheDegradedThresholdStartsTheTierZeroCooldownAndHoldsTheClaimableBacklog(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);
        $this->seedHealth('wh', EndpointState::Healthy, ctf: self::DEGRADED_THRESHOLD - 1);
        $this->createDelivery('evt-queued', 'wh', WebhookEventLogDefinition::STATUS_QUEUED);
        $this->createDelivery('evt-pending', 'wh', WebhookEventLogDefinition::STATUS_PENDING_RETRY);

        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientNetwork, 1);

        static::assertSame(EndpointState::Degraded, $result);
        $this->assertHealthTimestampAbout('wh', 'cooldown_until', self::COOLDOWN_TIER_0);
        // The claimable backlog is held for the ladder, on both mirrored tables.
        $this->assertDeliveryStatus('evt-queued', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->assertEventLogStatus('evt-queued', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->assertDeliveryStatus('evt-pending', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->assertEventLogStatus('evt-pending', WebhookEventLogDefinition::STATUS_PAUSED);
    }

    public function testAuthStreakSuspendsOnlyAtThreeConsecutiveFailuresWithoutASuccessInBetween(): void
    {
        // The spec sequence 401, 401, 2xx, 401, 401, 401: the 2xx resets the streak, so suspension
        // happens at the third post-reset failure — not at the third 401 overall.
        $this->seedWebhook('wh', active: true, errorCount: 0);

        $this->recordAuthFailures('wh', 2);
        $this->assertState('wh', EndpointState::Healthy);
        static::assertSame(2, $this->fetchCnf('wh'));

        $this->service->recordSuccess($this->ids->get('wh'));
        static::assertSame(0, $this->fetchCnf('wh'), 'any 2xx resets the auth streak');

        $this->recordAuthFailures('wh', 2);
        $this->assertState('wh', EndpointState::Healthy, 'the pre-success failures no longer count');
        static::assertSame(2, $this->fetchCnf('wh'));

        // A transient failure in between neither advances nor resets the auth streak.
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
        // Suspension holds the backlog — the row is paused, not deleted.
        $this->assertDeliveryStatus('evt-queued', WebhookEventLogDefinition::STATUS_PAUSED);
        $this->assertEventLogStatus('evt-queued', WebhookEventLogDefinition::STATUS_PAUSED);
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
        $this->assertEventLogStatus('evt-queued', WebhookEventLogDefinition::STATUS_PAUSED);
    }

    public function testTrialSuccessOnSuspendedDeEscalatesOneStateKeepingSuspendedSinceAndTheHeldBacklog(): void
    {
        $since = self::past();
        $this->seedWebhook('wh', active: false, errorCount: 5);
        $this->seedHealth('wh', EndpointState::Suspended, ctf: 5, cnf: 3, cycle: 4, cooldownUntil: self::past(), suspendedSince: $since);
        $this->createDelivery('evt-held', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->service->recordSuccess($this->ids->get('wh'));

        // One 2xx climbs exactly one state: DEGRADED at ladder tier 0 — HEALTHY is earned through the ladder.
        $this->assertState('wh', EndpointState::Degraded);
        static::assertSame(0, $this->fetchCycle('wh'));
        static::assertSame(0, $this->fetchCtf('wh'));
        static::assertSame(0, $this->fetchCnf('wh'));
        $this->assertHealthTimestampAbout('wh', 'cooldown_until', self::COOLDOWN_TIER_0);
        static::assertSame($since, $this->fetchHealthColumn('wh', 'suspended_since'), 'suspended_since survives the de-escalation');
        // The held backlog stays held until HEALTHY.
        $this->assertDeliveryStatus('evt-held', WebhookEventLogDefinition::STATUS_PAUSED);
        static::assertTrue($this->fetchActive('wh'), 'DEGRADED mirrors active = 1');
        static::assertSame(0, $this->fetchErrorCount('wh'), 'both streaks reset, so the mirrored count is 0');
    }

    public function testTrialSuccessOnDegradedPromotesToHealthyClearingEverythingAndResumingTheBacklog(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 5);
        // suspended_since carried over from an earlier SUSPENDED episode — HEALTHY must clear it.
        $this->seedHealth('wh', EndpointState::Degraded, ctf: 5, cnf: 1, cycle: 2, cooldownUntil: self::future(), suspendedSince: self::past());
        $this->createDelivery('evt-held', 'wh', WebhookEventLogDefinition::STATUS_PAUSED);

        $this->service->recordSuccess($this->ids->get('wh'));

        $this->assertState('wh', EndpointState::Healthy);
        static::assertSame(0, $this->fetchCtf('wh'));
        static::assertSame(0, $this->fetchCnf('wh'));
        static::assertSame(0, $this->fetchCycle('wh'));
        static::assertNull($this->fetchHealthColumn('wh', 'cooldown_until'));
        static::assertNull($this->fetchHealthColumn('wh', 'suspended_since'), 'reaching HEALTHY clears the suspension clock');
        // The held backlog resumes as claimable.
        $this->assertDeliveryStatus('evt-held', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        $this->assertEventLogStatus('evt-held', WebhookEventLogDefinition::STATUS_PENDING_RETRY);
        static::assertTrue($this->fetchActive('wh'));
        static::assertSame(0, $this->fetchErrorCount('wh'), 'recovery resets the BC error_count mirror');
    }

    public function testRecordSuccessClearsAHealthyPartialStreakOfBothCounters(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);
        $this->seedHealth('wh', EndpointState::Healthy, ctf: 3, cnf: 2);

        $this->service->recordSuccess($this->ids->get('wh'));

        $this->assertState('wh', EndpointState::Healthy);
        static::assertSame(0, $this->fetchCtf('wh'), 'a 2xx clears an in-progress transient streak so it cannot accumulate');
        static::assertSame(0, $this->fetchCnf('wh'), 'a 2xx clears an in-progress auth streak so it cannot accumulate');
    }

    public function testRecordSuccessReconcilesAStaleLegacyErrorCountWithoutAHealthRow(): void
    {
        // A webhook that accrued a legacy error_count before the flag, with no health row yet (fail-open
        // HEALTHY): a 2xx must reconcile the BC column to 0, matching trunk's per-success reset.
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

        // SUSPENDED → (2xx) → DEGRADED: the clock survives the de-escalation.
        $this->service->recordSuccess($this->ids->get('wh'));
        $this->assertState('wh', EndpointState::Degraded);
        static::assertSame($original, $this->fetchHealthColumn('wh', 'suspended_since'));

        // DEGRADED → (410) → SUSPENDED again: set-once — re-suspension never restarts the 7-day clock.
        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientEndpoint, 1);
        $this->assertState('wh', EndpointState::Suspended);
        static::assertSame($original, $this->fetchHealthColumn('wh', 'suspended_since'), 'a flap keeps the original suspension timestamp');

        // Climb back out: SUSPENDED → DEGRADED → HEALTHY clears it.
        $this->service->recordSuccess($this->ids->get('wh'));
        $this->service->recordSuccess($this->ids->get('wh'));
        $this->assertState('wh', EndpointState::Healthy);
        static::assertNull($this->fetchHealthColumn('wh', 'suspended_since'), 'only reaching HEALTHY clears the suspension clock');
    }

    public function testStragglerResultDuringTheCooldownLeavesTheCooldownUntouched(): void
    {
        $cooldown = self::future();
        $this->seedWebhook('wh', active: true, errorCount: 5);
        $this->seedHealth('wh', EndpointState::Degraded, ctf: 5, cycle: 2, cooldownUntil: $cooldown);

        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);

        $this->assertState('wh', EndpointState::Degraded);
        static::assertSame(2, $this->fetchCycle('wh'), 'a straggler result never advances the ladder');
        static::assertSame($cooldown, $this->fetchHealthColumn('wh', 'cooldown_until'), 'a straggler result never moves the cooldown');
    }

    public function testElapsedCooldownTrialFailureAdvancesOneTierToTheNextScheduleEntry(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 5);
        $this->seedHealth('wh', EndpointState::Degraded, ctf: 5, cycle: 0, cooldownUntil: self::past());

        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);

        static::assertSame(1, $this->fetchCycle('wh'));
        $this->assertHealthTimestampAbout('wh', 'cooldown_until', self::COOLDOWN_TIER_1);
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

    public function testThresholdCrossingDegradesExactlyOnceAndOnlyElapsedCooldownResultsAdvanceTheLadder(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);
        $this->seedHealth('wh', EndpointState::Healthy);

        for ($i = 0; $i < self::DEGRADED_THRESHOLD; ++$i) {
            $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);
        }
        $this->assertState('wh', EndpointState::Degraded);
        static::assertSame(5, $this->fetchCtf('wh'), 'crossing increments the counter to the threshold exactly once');
        static::assertSame(0, $this->fetchCycle('wh'), 'crossing into DEGRADED does not also advance the cycle');

        // A further transient lands while the fresh tier-0 cooldown still runs → a straggler, absorbed.
        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);
        static::assertSame(5, $this->fetchCtf('wh'), 'consecutive_transient_failures does not keep climbing in DEGRADED');
        static::assertSame(0, $this->fetchCycle('wh'), 'a result inside the cooldown window cannot double-advance the ladder');

        // With the cooldown elapsed the next result is a counted trial.
        $this->nudgeCooldownIntoPast('wh');
        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::TransientServer, 1);
        $this->assertState('wh', EndpointState::Degraded);
        static::assertSame(1, $this->fetchCycle('wh'), 'the trial result advances the degraded cycle');
    }

    public function testSuspensionFromTheAuthStreakHoldsAgainstARacingTransientResult(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);
        $this->seedHealth('wh', EndpointState::Healthy, cnf: self::NON_TRANSIENT_THRESHOLD - 1);

        $result = $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientAuth, 1);
        static::assertSame(EndpointState::Suspended, $result);

        // The racing 503's result lands after the suspension committed: the transient arm matches no
        // HEALTHY row and the fresh tier-0 cooldown absorbs it as a straggler — SUSPENDED wins.
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
        // Fail-open: no webhook_health row reads as HEALTHY, and the read-only gate must not upsert one.
        $this->seedWebhook('wh', active: true, errorCount: 0);

        static::assertSame(WebhookDispatchDecision::Deliver, $this->service->gateFor($this->ids->get('wh')));
        static::assertFalse(
            (bool) $this->connection->fetchOne('SELECT 1 FROM webhook_health WHERE webhook_id = :id', ['id' => $this->ids->getBytes('wh')]),
            'gateFor is a read — only a state-changing failure upserts the health row'
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
        // The closed blast radius (ADR §"per-webhook isolation"): siblings sharing event_name + url are
        // independent under the rework — suspending one must not touch the other.
        $this->seedWebhook('broken', active: true, errorCount: 0);
        $this->seedWebhook('sibling', active: true, errorCount: 0);

        $this->recordAuthFailures('broken', self::NON_TRANSIENT_THRESHOLD);

        $this->assertState('broken', EndpointState::Suspended);
        static::assertFalse($this->fetchActive('broken'));

        // The sibling never entered the model (no row = fail-open HEALTHY) and its BC mirror is untouched.
        static::assertFalse(
            (bool) $this->connection->fetchOne('SELECT 1 FROM webhook_health WHERE webhook_id = :id', ['id' => $this->ids->getBytes('sibling')]),
            'a sibling that never failed must not get a health row from its neighbour failing'
        );
        static::assertTrue($this->fetchActive('sibling'), 'the sibling stays active — no RelatedWebhooks blast radius');
        static::assertSame(0, $this->fetchErrorCount('sibling'));
    }

    private function recordAuthFailures(string $key, int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $this->service->recordFailure($this->ids->get($key), ErrorClassification::NonTransientAuth, 1);
        }
    }

    /**
     * The BC mirror formula (ADR BC table): HEALTHY mirrors 0; every other state mirrors the
     * dominant streak — GREATEST(transient, non-transient).
     */
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

    /**
     * Seeds a webhook_event_log row and its mirrored webhook_delivery row (the UNIQUE 1:1 pair the
     * outbox store works on), so transitions can prove the pause/resume flips on both tables.
     */
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

    private function nudgeCooldownIntoPast(string $key): void
    {
        $this->connection->executeStatement(
            'UPDATE webhook_health SET cooldown_until = :past WHERE webhook_id = :id',
            ['past' => self::past(), 'id' => $this->ids->getBytes($key)]
        );
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

    /**
     * For the nullable string/timestamp columns (endpoint_state, cooldown_until, suspended_since);
     * the integer counters have their own typed fetchers.
     */
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
        $status = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );
        static::assertSame($expectedStatus, $status, 'webhook_delivery status');
    }

    private function assertEventLogStatus(string $eventKey, string $expectedStatus): void
    {
        $status = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes($eventKey)]
        );
        static::assertSame($expectedStatus, $status, 'webhook_event_log status');
    }
}
