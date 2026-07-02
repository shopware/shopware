<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRecoveryResult;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Integration\App\TestAppServer;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * Exercises the whole app-secret-rotation lifecycle — install, rotation and recovery — against a fake app
 * server, checking both the saved state at each step and the two signatures actually sent over HTTP. The
 * pending state is produced by a real rotation that ends without a clear answer (a 5xx confirm), not by
 * setting it up directly, and the assertions check which secret signs the `shopware-shop-signature-previous`
 * header at each step. That header is the heart of the re-registration signature contract, which the unit
 * and command tests do not check at the HTTP level.
 *
 * @internal
 */
#[Package('framework')]
class AppSecretRotationEndToEndTest extends TestCase
{
    use AppSystemTestBehaviour;
    use GuzzleTestClientBehaviour;

    private const FIXTURE_APP_DIR = __DIR__ . '/../Manifest/_fixtures/test';
    private const FIXTURE_APP_NAME = 'test';

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    private AppSecretRotationService $rotationService;

    private Context $context;

    private string $shopUrl;

    protected function setUp(): void
    {
        $this->appRepository = static::getContainer()->get('app.repository');
        $this->rotationService = static::getContainer()->get(AppSecretRotationService::class);
        $this->context = Context::createDefaultContext();
        $this->shopUrl = (string) EnvironmentHelper::getVariable('APP_URL');
    }

    // §1 — Happy path: the confirm succeeds and the new, app-minted secret is committed.

    public function testRotationReRegistersAndCommitsTheAppMintedSecret(): void
    {
        $app = $this->installApp();
        $secretBeforeRotation = $app->getAppSecret();
        // The auto-responding app server commits its own secret during the initial registration.
        static::assertSame(TestAppServer::APP_SECRET, $secretBeforeRotation);

        $rotatedSecret = 'rotated-secret';
        $this->enqueueReRegistrationAttempt(minting: $rotatedSecret, confirmResponse: new Response(200));

        $this->rotationService->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_API);

        $rotated = $this->getInstalledApp();
        static::assertSame($rotatedSecret, $rotated->getAppSecret());
        static::assertNull($rotated->getUnconfirmedAppSecrets());

        // The confirm proves the app holds the new secret (the new signature) and proves we are the same shop
        // by also signing with the secret the app held before (the previous signature).
        $this->assertConfirmCarriesDualSignature(
            $this->lastConfirm(),
            newSecret: $rotatedSecret,
            previousSecret: $secretBeforeRotation,
        );
    }

    // §2 — Ambiguous failures: a 5xx/timeout gives no clear answer, so the pending secret is kept and the
    // app stays recoverable.

    public function testAmbiguousConfirmTimeoutLeavesAPendingSecretLikeAServerError(): void
    {
        $app = $this->installApp();
        $committedSecret = $app->getAppSecret();
        static::assertNotNull($committedSecret);

        $pendingSecret = 'pending-secret';

        // The confirm fails as a transport timeout — a ConnectException with NO response — rather than a 5xx
        // with a body. That hits a different arm of registrationFailedFromResponse (getResponse() === null),
        // but it must still be treated as an ambiguous outcome (the app may have switched), so the pending
        // secret is retained, not dropped the way a definitive 4xx rejection would be.
        $confirmTimeout = new ConnectException('Connection timed out', new Request('POST', TestAppServer::CONFIRMATION_URL));
        $this->enqueueReRegistrationAttempt(minting: $pendingSecret, confirmResponse: $confirmTimeout);

        try {
            $this->rotationService->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_API);
            static::fail('An ambiguous confirm timeout must surface as a registration failure.');
        } catch (AppRegistrationException $e) {
            // expected — the outcome is unknown, the operator must recover
            static::assertSame(AppException::REGISTRATION_FAILED, $e->getErrorCode());
        }

        $afterRotation = $this->getInstalledApp();
        static::assertSame($committedSecret, $afterRotation->getAppSecret(), 'a timeout must not commit the new secret');
        static::assertSame([$pendingSecret], $afterRotation->getUnconfirmedAppSecrets(), 'a timeout retains the pending secret, like a 5xx');
    }

    public function testAmbiguousFirstInstallKeepsTheAppSoTheSecretCanBeRecovered(): void
    {
        // Drives the REAL install path (not a seeded row): a first install whose handshake succeeds — the app
        // mints and persists a secret — but whose confirm fails ambiguously (5xx). The app may already hold
        // that secret, and a confirmed app refuses a fresh re-registration, so deleting the row here (the old
        // behaviour) stranded the install permanently. The row and its pending secret must survive instead.
        $manifest = Manifest::createFromXmlFile(self::FIXTURE_APP_DIR . '/manifest.xml');

        $firstInstallSecret = 'first-install-secret';
        $this->enqueueReRegistrationAttempt(minting: $firstInstallSecret, confirmResponse: new Response(500));

        try {
            static::getContainer()->get(AppLifecycle::class)->install($manifest, new AppInstallParameters(), $this->context);
            static::fail('An ambiguous first install must surface as a registration failure.');
        } catch (AppRegistrationException $e) {
            // expected — the confirm outcome is unknown
            static::assertSame(AppException::REGISTRATION_FAILED, $e->getErrorCode());
        }

        try {
            $halfInstalled = $this->getInstalledApp();
            // The row and the pending secret the app may hold survive — they are not deleted.
            static::assertNull($halfInstalled->getAppSecret(), 'a first install never commits a secret');
            static::assertSame([$firstInstallSecret], $halfInstalled->getUnconfirmedAppSecrets());

            // Recovery re-registers against that pending secret (its only candidate) and commits a fresh one.
            $recoveredSecret = 'recovered-secret';
            $this->enqueueReRegistrationAttempt(minting: $recoveredSecret, confirmResponse: new Response(200));
            $this->rotationService->recoverNow($halfInstalled->getId(), $this->context);

            $recovered = $this->getInstalledApp();
            static::assertSame($recoveredSecret, $recovered->getAppSecret());
            static::assertNull($recovered->getUnconfirmedAppSecrets());
            // The confirm proves the new secret and authenticates as the pending secret the app held.
            $this->assertConfirmCarriesDualSignature(
                $this->lastConfirm(),
                newSecret: $recoveredSecret,
                previousSecret: $firstInstallSecret,
            );
        } finally {
            // Installed via AppLifecycle directly, so the trait's #[After] cleanup does not track this app.
            static::getContainer()->get(Connection::class)->executeStatement(
                'DELETE FROM app WHERE name = :name',
                ['name' => self::FIXTURE_APP_NAME]
            );
        }
    }

    public function testAmbiguousRotationLeavesPendingThenRecoveryFallsBackToTheCurrentSecret(): void
    {
        $app = $this->installApp();
        $committedSecret = $app->getAppSecret();
        static::assertNotNull($committedSecret);

        $pendingSecret = 'pending-secret';

        // Rotation: the app gives us a new secret but the confirm fails without a clear answer (5xx), so we
        // cannot tell whether the app switched. The new secret is left pending; the active secret stays put.
        $this->enqueueReRegistrationAttempt(minting: $pendingSecret, confirmResponse: new Response(500));

        $this->rotateExpectingAmbiguousFailure($app->getId());

        $afterRotation = $this->getInstalledApp();
        static::assertSame($committedSecret, $afterRotation->getAppSecret());
        static::assertSame([$pendingSecret], $afterRotation->getUnconfirmedAppSecrets());
        $this->assertConfirmCarriesDualSignature(
            $this->lastConfirm(),
            newSecret: $pendingSecret,
            previousSecret: $committedSecret,
        );

        // Recovery re-registers, signing with the pending secret first. Here the app never switched (it still
        // holds the old secret), so it rejects the pending one (4xx); recovery then falls back to the current
        // secret, which the app accepts.
        $recoveredSecret = 'recovered-after-fallback';
        $this->enqueueReRegistrationAttempt(minting: $recoveredSecret, confirmResponse: new Response(403));
        $this->enqueueReRegistrationAttempt(minting: $recoveredSecret, confirmResponse: new Response(200));

        $this->rotationService->recoverNow($app->getId(), $this->context);

        $recovered = $this->getInstalledApp();
        static::assertSame($recoveredSecret, $recovered->getAppSecret());
        static::assertNull($recovered->getUnconfirmedAppSecrets());

        // Both attempts mint the same recovered secret, so only the previous-signature differs: the first
        // attempt authenticates as the pending secret, the fall-back as the committed one.
        [$firstAttemptConfirm, $fallbackConfirm] = $this->lastConfirms(2);
        $this->assertConfirmCarriesDualSignature(
            $firstAttemptConfirm,
            newSecret: $recoveredSecret,
            previousSecret: $pendingSecret,
        );
        $this->assertConfirmCarriesDualSignature(
            $fallbackConfirm,
            newSecret: $recoveredSecret,
            previousSecret: $committedSecret,
        );
    }

    public function testRecoveryCommitsWithThePendingSecretWhenTheAppPromotedIt(): void
    {
        $app = $this->installApp();
        $committedSecret = $app->getAppSecret();
        static::assertNotNull($committedSecret);

        $pendingSecret = 'pending-secret';
        $this->enqueueReRegistrationAttempt(minting: $pendingSecret, confirmResponse: new Response(500));
        $this->rotateExpectingAmbiguousFailure($app->getId());

        static::assertSame([$pendingSecret], $this->getInstalledApp()->getUnconfirmedAppSecrets());

        // This time the app did switch to the pending secret, so it accepts the first recovery attempt.
        $recoveredSecret = 'recovered-with-pending';
        $confirmsBeforeRecovery = $this->confirmCount();
        $this->enqueueReRegistrationAttempt(minting: $recoveredSecret, confirmResponse: new Response(200));

        $this->rotationService->recoverNow($app->getId(), $this->context);

        $recovered = $this->getInstalledApp();
        static::assertSame($recoveredSecret, $recovered->getAppSecret());
        static::assertNull($recovered->getUnconfirmedAppSecrets());

        // The pending secret was accepted on the first try, so recovery never fell back to the current secret.
        static::assertSame($confirmsBeforeRecovery + 1, $this->confirmCount(), 'recovery accepted the first candidate, so it made a single attempt (no fallback)');
        $this->assertConfirmCarriesDualSignature(
            $this->lastConfirm(),
            newSecret: $recoveredSecret,
            previousSecret: $pendingSecret,
        );
    }

    public function testTransientRecoveryRejectionKeepsPendingSecretsForRetry(): void
    {
        $app = $this->installApp();
        $committedSecret = $app->getAppSecret();
        static::assertNotNull($committedSecret);

        $pendingSecret = 'pending-secret';
        $this->enqueueReRegistrationAttempt(minting: $pendingSecret, confirmResponse: new Response(500));
        $this->rotateExpectingAmbiguousFailure($app->getId());

        static::assertSame([$pendingSecret], $this->getInstalledApp()->getUnconfirmedAppSecrets());

        // A transient proxy/WAF 4xx can look like a definitive rejection. Recovery exhausts both candidates
        // and reports "claimed", but it must keep the pending record so the operator can retry after the
        // transient clears.
        $this->enqueueReRegistrationAttempt(minting: 'transient-recovery-secret', confirmResponse: new Response(403));
        $this->enqueueReRegistrationAttempt(minting: 'transient-recovery-secret', confirmResponse: new Response(403));

        static::assertSame(
            AppSecretRecoveryResult::Claimed,
            $this->rotationService->recoverNow($app->getId(), $this->context),
            'Recovery must report claimed when every candidate receives a 4xx.'
        );

        $afterTransientFailure = $this->getInstalledApp();
        static::assertSame($committedSecret, $afterTransientFailure->getAppSecret());
        static::assertSame([$pendingSecret], $afterTransientFailure->getUnconfirmedAppSecrets());

        // Once the transient clears, the same pending record lets recovery self-heal.
        $recoveredSecret = 'recovered-after-transient';
        $this->enqueueReRegistrationAttempt(minting: $recoveredSecret, confirmResponse: new Response(200));

        $this->rotationService->recoverNow($app->getId(), $this->context);

        $recovered = $this->getInstalledApp();
        static::assertSame($recoveredSecret, $recovered->getAppSecret());
        static::assertNull($recovered->getUnconfirmedAppSecrets());
        $this->assertConfirmCarriesDualSignature(
            $this->lastConfirm(),
            newSecret: $recoveredSecret,
            previousSecret: $pendingSecret,
        );
    }

    public function testRecoveryReRegistersAFirstRegistrationThatNeverCommitted(): void
    {
        $app = $this->installApp();
        static::assertNotNull($app->getAppSecret());

        // A first registration that minted a secret (the app persisted it at the handshake) but was killed
        // before committing leaves appSecret=null with a pending secret. This state cannot be produced by a
        // real rotation — which needs a committed secret — so seed it directly to mirror the crashed-install
        // row, then recover against it.
        $appHeldSecret = 'app-held-secret';
        $this->seedCrashedFirstRegistration($app->getId(), $appHeldSecret);

        // With no committed secret there is no fallback candidate, so recovery makes a single attempt
        // (handshake + confirm) and must authenticate as the pending secret the app is presumed to hold.
        $recoveredSecret = 'recovered-first-registration';
        $confirmsBeforeRecovery = $this->confirmCount();
        $this->enqueueReRegistrationAttempt(minting: $recoveredSecret, confirmResponse: new Response(200));

        $this->rotationService->recoverNow($app->getId(), $this->context);

        $recovered = $this->getInstalledApp();
        static::assertSame($recoveredSecret, $recovered->getAppSecret());
        static::assertNull($recovered->getUnconfirmedAppSecrets());

        // Exactly one re-registration attempt: the pending secret is the only candidate (no committed fallback).
        static::assertSame($confirmsBeforeRecovery + 1, $this->confirmCount(), 'recovery accepted the first candidate, so it made a single attempt (no fallback)');
        $this->assertConfirmCarriesDualSignature(
            $this->lastConfirm(),
            newSecret: $recoveredSecret,
            previousSecret: $appHeldSecret,
        );
    }

    public function testRecoveryRollsBackTheIntegrationAndPreservesThePendingWhenAnAttemptFailsAmbiguously(): void
    {
        $app = $this->installApp();
        $committedSecret = $app->getAppSecret();
        static::assertNotNull($committedSecret);

        $pendingSecret = 'pending-secret';
        $this->enqueueReRegistrationAttempt(minting: $pendingSecret, confirmResponse: new Response(500));
        $this->rotateExpectingAmbiguousFailure($app->getId());

        $afterRotation = $this->getInstalledApp();
        static::assertSame([$pendingSecret], $afterRotation->getUnconfirmedAppSecrets());
        $integrationBeforeRecovery = $afterRotation->getIntegrationId();

        // Recovery's first attempt cannot even complete the handshake (a transport failure, not a rejection):
        // the outcome is unknown. No fresh credentials were delivered, so the integration switch is undone and
        // the recovery record is preserved for a retry rather than left broken. Only a single 5xx is enqueued —
        // it answers the handshake, so the confirm is never reached.
        $this->enqueueFailedHandshake(new Response(500));
        try {
            $this->rotationService->recoverNow($app->getId(), $this->context);
            static::fail('An ambiguous recovery attempt must surface as a registration failure.');
        } catch (AppRegistrationException $e) {
            static::assertSame(AppException::REGISTRATION_FAILED, $e->getErrorCode());
        }

        $afterRecovery = $this->getInstalledApp();
        static::assertSame($committedSecret, $afterRecovery->getAppSecret());
        static::assertSame([$pendingSecret], $afterRecovery->getUnconfirmedAppSecrets());
        static::assertSame($integrationBeforeRecovery, $afterRecovery->getIntegrationId());
    }

    // §3 — Hard failures: a 4xx rejection means the registration is claimed, so the operator must change the
    // shop id.

    public function testRecoveryNotifiesWhenAFirstRegistrationsPendingSecretIsRejected(): void
    {
        $app = $this->installApp();

        // First-registration crash state again, but this time the app does not hold the pending secret.
        $rejectedPendingSecret = 'rejected-pending-secret';
        $this->seedCrashedFirstRegistration($app->getId(), $rejectedPendingSecret);

        // The app rejects the pending secret (4xx) and there is no committed secret to fall back to, so the
        // registration cannot be recovered programmatically — recovery notifies (claimed) for operator action
        // rather than silently failing or losing state.
        $this->enqueueReRegistrationAttempt(minting: 'rejected', confirmResponse: new Response(403));

        static::assertSame(
            AppSecretRecoveryResult::Claimed,
            $this->rotationService->recoverNow($app->getId(), $this->context),
            'Recovery must report the registration as claimed when the only candidate is rejected.'
        );

        $reverted = $this->getInstalledApp();
        // No committed secret to restore, and the pending record is retained for a retry or explicit discard.
        static::assertNull($reverted->getAppSecret());
        static::assertSame([$rejectedPendingSecret], $reverted->getUnconfirmedAppSecrets());
    }

    public function testRecoveryRevertsAndRecommendsShopIdChangeWhenBothSecretsAreRejected(): void
    {
        $app = $this->installApp();
        $committedSecret = $app->getAppSecret();
        static::assertNotNull($committedSecret);

        $this->enqueueReRegistrationAttempt(minting: 'pending-secret', confirmResponse: new Response(500));
        $this->rotateExpectingAmbiguousFailure($app->getId());

        // Neither the pending nor the committed secret is accepted: core no longer holds a secret the app trusts.
        $this->enqueueReRegistrationAttempt(minting: 'recovery-rejected', confirmResponse: new Response(403));
        $this->enqueueReRegistrationAttempt(minting: 'recovery-rejected', confirmResponse: new Response(403));

        static::assertSame(
            AppSecretRecoveryResult::Claimed,
            $this->rotationService->recoverNow($app->getId(), $this->context),
            'Recovery must report the registration as claimed when both secrets are rejected.'
        );

        // The active secret is left on the last known-good value and the pending record is retained for a
        // retry or explicit discard.
        $reverted = $this->getInstalledApp();
        static::assertSame($committedSecret, $reverted->getAppSecret());
        static::assertSame(['pending-secret'], $reverted->getUnconfirmedAppSecrets());
    }

    private function installApp(): AppEntity
    {
        $this->loadAppsFromDir(self::FIXTURE_APP_DIR);

        return $this->getInstalledApp();
    }

    private function getInstalledApp(): AppEntity
    {
        $criteria = (new Criteria())->addAssociation('integration');

        $app = $this->appRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(AppEntity::class, $app);

        return $app;
    }

    /**
     * Seed the row a first registration leaves when it minted a secret (the app persisted it at the handshake)
     * but the process was killed before committing: no active secret, one pending secret the app may hold. A
     * real rotation cannot produce this — it always starts from a committed secret — so the tests write it
     * directly to drive recovery against a crashed install.
     */
    private function seedCrashedFirstRegistration(string $appId, string $seededPendingSecret): void
    {
        $this->context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($appId, $seededPendingSecret): void {
            $this->appRepository->update([[
                'id' => $appId,
                'appSecret' => null,
                'unconfirmedAppSecrets' => [$seededPendingSecret],
            ]], $context);
        });
    }

    /**
     * Run a rotation whose confirm was already set up to fail without a clear answer, asserting it surfaces as
     * a registration failure. The operator is then expected to recover; what survives in the database is
     * asserted by the caller.
     */
    private function rotateExpectingAmbiguousFailure(string $appId): void
    {
        try {
            $this->rotationService->rotateNow($appId, $this->context, AppSecretRotationService::TRIGGER_API);
            static::fail('An ambiguous rotation must surface as a registration failure.');
        } catch (AppRegistrationException $e) {
            // expected — the outcome is unknown, the operator must recover
            static::assertSame(AppException::REGISTRATION_FAILED, $e->getErrorCode());
        }
    }

    /**
     * Queue the two HTTP responses one re-registration attempt consumes, in order: the app server's handshake
     * response that mints $minting, then the confirm outcome (a 2xx accept, a 4xx/5xx, or a transport
     * exception). The confirm is read back by role with {@see confirmRequests()}.
     */
    private function enqueueReRegistrationAttempt(string $minting, ResponseInterface|\Exception $confirmResponse): void
    {
        $this->enqueueHandshakeMinting($minting);
        $this->appendNewResponse($confirmResponse);
    }

    /**
     * Queue a handshake response that itself fails, so the attempt never reaches the confirm. Used to drive the
     * "the handshake could not even complete" branch, distinct from a confirm that fails.
     */
    private function enqueueFailedHandshake(ResponseInterface $handshakeResponse): void
    {
        $this->appendNewResponse($handshakeResponse);
    }

    private function enqueueHandshakeMinting(string $mintedSecret): void
    {
        $shopId = static::getContainer()->get(ShopIdProvider::class)->getShopId()->id;
        $proof = hash_hmac('sha256', $shopId . $this->shopUrl . self::FIXTURE_APP_NAME, TestAppServer::TEST_SETUP_SECRET);

        $this->appendNewResponse(new Response(200, [], json_encode([
            'proof' => $proof,
            'secret' => $mintedSecret,
            'confirmation_url' => TestAppServer::CONFIRMATION_URL,
        ], \JSON_THROW_ON_ERROR)));
    }

    /**
     * Every confirm sent so far, oldest first. The handshake that opens each re-registration is a GET, so the
     * POSTs in the request log are the confirms.
     *
     * @return list<RequestInterface>
     */
    private function confirmRequests(): array
    {
        $confirms = [];
        for ($i = 0, $count = $this->getRequestCount(); $i < $count; ++$i) {
            $request = $this->getPastRequest($i);
            if ($request->getMethod() === 'POST') {
                $confirms[] = $request;
            }
        }

        return $confirms;
    }

    /**
     * The most recent confirm sent.
     */
    private function lastConfirm(): RequestInterface
    {
        $confirms = $this->confirmRequests();
        static::assertNotEmpty($confirms, 'expected at least one confirm to have been sent');

        return $confirms[array_key_last($confirms)];
    }

    /**
     * The last $count confirms sent, oldest first.
     *
     * @return list<RequestInterface>
     */
    private function lastConfirms(int $count): array
    {
        $confirms = $this->confirmRequests();
        static::assertGreaterThanOrEqual($count, \count($confirms), \sprintf('expected at least %d confirms to have been sent', $count));

        return \array_slice($confirms, -$count);
    }

    /**
     * How many confirms have been sent so far.
     */
    private function confirmCount(): int
    {
        return \count($this->confirmRequests());
    }

    /**
     * A re-registration confirm carries two signatures over the same payload: `shopware-shop-signature` signed
     * with the newly minted secret (proves the app handed us that secret) and `shopware-shop-signature-previous`
     * signed with the secret the app held before (proves we are the shop the app already knows). Both must be
     * present and correct, or only the original initiator could not confirm a re-registration.
     */
    private function assertConfirmCarriesDualSignature(RequestInterface $confirm, string $newSecret, string $previousSecret): void
    {
        static::assertSame('POST', $confirm->getMethod());

        $json = $this->confirmPayloadJson($confirm);
        static::assertSame(hash_hmac('sha256', $json, $newSecret), $confirm->getHeaderLine('shopware-shop-signature'));
        static::assertSame(hash_hmac('sha256', $json, $previousSecret), $confirm->getHeaderLine('shopware-shop-signature-previous'));
    }

    /**
     * The exact JSON the confirm signatures were computed over. The body is already JSON, but production signs
     * `json_encode($payload)` of the decoded array, so we round-trip through decode→encode to reproduce that
     * canonical form before recomputing the HMAC.
     */
    private function confirmPayloadJson(RequestInterface $confirm): string
    {
        $payload = json_decode($confirm->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);

        return json_encode($payload, \JSON_THROW_ON_ERROR);
    }
}
