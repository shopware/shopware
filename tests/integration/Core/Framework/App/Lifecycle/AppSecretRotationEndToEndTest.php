<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Command\AppPrinter;
use Shopware\Core\Framework\App\Command\InstallAppCommand;
use Shopware\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Integration\App\TestAppServer;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
    use EventDispatcherBehaviour;
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
            static::fail('An ambiguous confirm timeout must surface as a failure.');
        } catch (AppException $e) {
            // expected — the outcome is unknown, the operator must recover
            static::assertSame(AppException::APP_SECRET_RECOVERY_FAILED, $e->getErrorCode());
        }

        $afterRotation = $this->getInstalledApp();
        static::assertSame($committedSecret, $afterRotation->getAppSecret(), 'a timeout must not commit the new secret');
        static::assertSame([$pendingSecret], $afterRotation->getUnconfirmedAppSecrets(), 'a timeout retains the pending secret, like a 5xx');
    }

    public function testAmbiguousFirstInstallKeepsTheAppSoTheSecretCanBeRecovered(): void
    {
        $command = new CommandTester($this->createInstallCommand());
        $installedEvents = new \ArrayObject();
        $eventDispatcher = static::getContainer()->get('event_dispatcher');
        static::assertInstanceOf(EventDispatcherInterface::class, $eventDispatcher);
        $this->addEventListener($eventDispatcher, AppInstalledEvent::class, static function (AppInstalledEvent $event) use ($installedEvents): void {
            $installedEvents->append($event);
        });

        // First CLI attempt: the app mints and persists a secret, but confirmation fails ambiguously. The row
        // and pending candidate must survive, while the install lifecycle has not run yet.
        $firstInstallSecret = 'first-install-secret';
        $this->enqueueReRegistrationAttempt(minting: $firstInstallSecret, confirmResponse: new Response(500));

        try {
            $command->execute(['name' => self::FIXTURE_APP_NAME, '-f' => true]);
            static::fail('An ambiguous first install must surface as a registration failure.');
        } catch (AppRegistrationException $e) {
            static::assertSame(AppException::REGISTRATION_FAILED, $e->getErrorCode());
        }

        try {
            $halfInstalled = $this->getInstalledApp();
            static::assertNull($halfInstalled->getAppSecret(), 'a first install never commits a secret');
            static::assertSame([$firstInstallSecret], $halfInstalled->getUnconfirmedAppSecrets());
            static::assertCount(0, $installedEvents, 'the failed attempt must not emit the installed lifecycle event');

            // Second CLI attempt repairs credentials and resumes the skipped lifecycle exactly once. --activate
            // is honoured for a half-finished install, unlike repair of an already completed app.
            $recoveredSecret = 'recovered-secret';
            $this->enqueueReRegistrationAttempt(minting: $recoveredSecret, confirmResponse: new Response(200));
            static::assertSame(Command::SUCCESS, $command->execute([
                'name' => self::FIXTURE_APP_NAME,
                '-f' => true,
                '-a' => true,
            ]));

            $recovered = $this->getInstalledApp();
            static::assertSame($recoveredSecret, $recovered->getAppSecret());
            static::assertNull($recovered->getUnconfirmedAppSecrets());
            static::assertTrue($recovered->isActive());
            static::assertCount(1, $installedEvents);
            // The confirm proves the new secret and authenticates as the pending secret the app held.
            $this->assertConfirmCarriesDualSignature(
                $this->lastConfirm(),
                newSecret: $recoveredSecret,
                previousSecret: $firstInstallSecret,
            );
        } finally {
            // Installed through a standalone command, so the trait's #[After] cleanup does not track this app.
            static::getContainer()->get(Connection::class)->executeStatement(
                'DELETE FROM app WHERE name = :name',
                ['name' => self::FIXTURE_APP_NAME]
            );
        }
    }

    public function testRotationRefusesAnInstallationThatNeverFinishedSoItStaysResumable(): void
    {
        $command = new CommandTester($this->createInstallCommand());
        $installedEvents = new \ArrayObject();
        $eventDispatcher = static::getContainer()->get('event_dispatcher');
        static::assertInstanceOf(EventDispatcherInterface::class, $eventDispatcher);
        $this->addEventListener($eventDispatcher, AppInstalledEvent::class, static function (AppInstalledEvent $event) use ($installedEvents): void {
            $installedEvents->append($event);
        });

        $firstInstallSecret = 'first-install-secret';
        $this->enqueueReRegistrationAttempt(minting: $firstInstallSecret, confirmResponse: new Response(500));

        try {
            $command->execute(['name' => self::FIXTURE_APP_NAME, '-f' => true]);
            static::fail('An ambiguous first install must surface as a registration failure.');
        } catch (AppRegistrationException) {
            // expected — the lifecycle never ran and the unconfirmed secret is the only marker saying so
        }

        try {
            $halfInstalled = $this->getInstalledApp();
            static::assertNull($halfInstalled->getAppSecret());
            static::assertSame([$firstInstallSecret], $halfInstalled->getUnconfirmedAppSecrets());

            // A rotation only repairs credentials, and committing clears the unconfirmed list — the marker
            // the installation needs to resume. It must refuse before contacting the app rather than
            // silently leaving an app that can never finish installing.
            $requestsBeforeRotation = $this->getRequestCount();

            try {
                $this->rotationService->rotateNow($halfInstalled->getId(), $this->context, AppSecretRotationService::TRIGGER_CLI);
                static::fail('A rotation must not repair an installation that never finished.');
            } catch (AppException $e) {
                static::assertSame(AppException::APP_INSTALLATION_INCOMPLETE, $e->getErrorCode());
            }

            static::assertSame($requestsBeforeRotation, $this->getRequestCount(), 'the refusal must happen before the app is contacted');
            static::assertSame([$firstInstallSecret], $this->getInstalledApp()->getUnconfirmedAppSecrets());

            // ...so re-running the install still completes the interrupted lifecycle.
            $recoveredSecret = 'recovered-secret';
            $this->enqueueReRegistrationAttempt(minting: $recoveredSecret, confirmResponse: new Response(200));
            static::assertSame(Command::SUCCESS, $command->execute([
                'name' => self::FIXTURE_APP_NAME,
                '-f' => true,
                '-a' => true,
            ]));

            $recovered = $this->getInstalledApp();
            static::assertSame($recoveredSecret, $recovered->getAppSecret());
            static::assertNull($recovered->getUnconfirmedAppSecrets());
            static::assertTrue($recovered->isActive());
            static::assertCount(1, $installedEvents, 'the skipped install lifecycle must run exactly once');
        } finally {
            // Installed through a standalone command, so the trait's #[After] cleanup does not track this app.
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

        $this->rotationService->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_CLI);

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

    public function testRecoveryFallsBackWhenTheAppRefusesTheUnconfirmedSecretWithoutAClearAnswer(): void
    {
        $app = $this->installApp();
        $committedSecret = $app->getAppSecret();
        static::assertNotNull($committedSecret);

        $pendingSecret = 'pending-secret';
        $this->enqueueReRegistrationAttempt(minting: $pendingSecret, confirmResponse: new Response(500));
        $this->rotateExpectingAmbiguousFailure($app->getId());

        static::assertSame([$pendingSecret], $this->getInstalledApp()->getUnconfirmedAppSecrets());

        // The app never switched, so this handshake is signed with a secret it does not trust — and it refuses
        // with a 500, which is indistinguishable from a faulty server. The walk must still reach the committed
        // secret.
        $recoveredSecret = 'recovered-after-refusal';
        $this->enqueueFailedHandshake(new Response(500, [], '{"errors":[{"status":"500","message":"Signature could not be verified"}]}'));
        $this->enqueueReRegistrationAttempt(minting: $recoveredSecret, confirmResponse: new Response(200));

        $this->rotationService->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_CLI);

        $recovered = $this->getInstalledApp();
        static::assertSame($recoveredSecret, $recovered->getAppSecret());
        static::assertNull($recovered->getUnconfirmedAppSecrets());

        // The refused candidate never reached a confirm, so the only confirm sent authenticates as the
        // committed secret — the proof that the walk advanced past the refusal instead of stopping on it.
        $this->assertConfirmCarriesDualSignature(
            $this->lastConfirm(),
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

        $this->rotationService->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_CLI);

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
        // and fails, but it must keep the pending record so the operator can retry after the transient clears.
        $this->enqueueReRegistrationAttempt(minting: 'transient-recovery-secret', confirmResponse: new Response(403));
        $this->enqueueReRegistrationAttempt(minting: 'transient-recovery-secret', confirmResponse: new Response(403));

        try {
            $this->rotationService->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_CLI);
            static::fail('Recovery must fail when every candidate receives a 4xx.');
        } catch (AppException $e) {
            static::assertSame(AppException::APP_SECRET_RECOVERY_FAILED, $e->getErrorCode());
        }

        $afterTransientFailure = $this->getInstalledApp();
        static::assertSame($committedSecret, $afterTransientFailure->getAppSecret());
        static::assertSame([$pendingSecret], $afterTransientFailure->getUnconfirmedAppSecrets());

        // Once the transient clears, the same pending record lets recovery self-heal.
        $recoveredSecret = 'recovered-after-transient';
        $this->enqueueReRegistrationAttempt(minting: $recoveredSecret, confirmResponse: new Response(200));

        $this->rotationService->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_CLI);

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

        // Driven as the installation's own recovery, the way AppManager reaches this state: a bare rotation
        // refuses an unfinished install, because committing clears the marker app:install needs to resume it.
        $this->rotationService->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_RECOVERY);

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

    public function testRecoveryKeepsThePendingAndSwitchesBackWhenNoCandidateReachesTheApp(): void
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

        // Recovery's attempts cannot even complete the handshake (a transport failure, not a rejection): the
        // outcome is unknown, so the recovery record is preserved for a later retry. One 5xx per candidate —
        // it answers the handshake, so the confirm is never reached.
        $requestsBeforeRecovery = $this->getRequestCount();
        $this->enqueueFailedHandshake(new Response(500));
        $this->enqueueFailedHandshake(new Response(500));
        try {
            $this->rotationService->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_CLI);
            static::fail('An ambiguous recovery attempt must surface as a failure.');
        } catch (AppException $e) {
            static::assertSame(AppException::APP_SECRET_RECOVERY_FAILED, $e->getErrorCode());
        }

        // Both candidates were handed to the app. An app that cannot verify a signature answers 500 just as
        // readily as 4xx, so a candidate failing without a clear answer must not end the walk.
        static::assertSame(
            2,
            $this->getRequestCount() - $requestsBeforeRecovery,
            'recovery must hand every candidate secret to the app'
        );

        $afterRecovery = $this->getInstalledApp();
        static::assertSame($committedSecret, $afterRecovery->getAppSecret());
        static::assertSame([$pendingSecret], $afterRecovery->getUnconfirmedAppSecrets());
        // No candidate got past the handshake, so no confirm handed the app the integration this attempt
        // created. Switching back leaves the app on the integration it may already hold, instead of stranding
        // it on one that a cleanup will delete.
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
        // registration cannot be recovered programmatically — recovery fails loudly for operator action
        // rather than silently failing or losing state.
        $this->enqueueReRegistrationAttempt(minting: 'rejected', confirmResponse: new Response(403));

        try {
            $this->rotationService->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_RECOVERY);
            static::fail('Recovery must fail when the only candidate is rejected.');
        } catch (AppException $e) {
            static::assertSame(AppException::APP_SECRET_RECOVERY_FAILED, $e->getErrorCode());
        }

        $reverted = $this->getInstalledApp();
        // No committed secret to restore, and the pending record is retained for a retry or explicit discard.
        static::assertNull($reverted->getAppSecret());
        static::assertSame([$rejectedPendingSecret], $reverted->getUnconfirmedAppSecrets());
    }

    // §4 — Uninstall in the middle of a rotation: the app keeps a secret this shop never committed, so the
    // reinstall has to offer the pending candidate.

    public function testReinstallAfterUninstallingMidRotationRecoversTheApp(): void
    {
        $app = $this->installApp();
        $committedSecret = $app->getAppSecret();
        static::assertNotNull($committedSecret);

        // The app adopted this secret but could not confirm it back: the shop stays on $committedSecret.
        $adoptedSecret = 'adopted-but-unconfirmed';
        $this->enqueueReRegistrationAttempt(minting: $adoptedSecret, confirmResponse: new Response(500));
        $this->rotateExpectingAmbiguousFailure($app->getId());
        static::assertSame([$adoptedSecret], $this->getInstalledApp()->getUnconfirmedAppSecrets());

        static::getContainer()->get(AppLifecycle::class)->uninstall(
            self::FIXTURE_APP_NAME,
            ['id' => $app->getId()],
            $this->context
        );

        $deletedApps = static::getContainer()->get(DeletedAppsGateway::class);
        static::assertSame($committedSecret, $deletedApps->getDeletedAppSecret(self::FIXTURE_APP_NAME));
        static::assertSame([$adoptedSecret], $deletedApps->getDeletedAppUnconfirmedSecrets(self::FIXTURE_APP_NAME));

        $command = new CommandTester($this->createInstallCommand());

        try {
            $recoveredSecret = 'recovered-after-reinstall';
            $this->enqueueReRegistrationAttempt(minting: $recoveredSecret, confirmResponse: new Response(200));
            $confirmsBeforeReinstall = $this->confirmCount();

            static::assertSame(Command::SUCCESS, $command->execute(['name' => self::FIXTURE_APP_NAME, '-f' => true]));

            static::assertSame(
                $confirmsBeforeReinstall + 1,
                $this->confirmCount(),
                'the pending candidate is offered first, so no attempt is spent on the committed secret'
            );

            $recovered = $this->getInstalledApp();
            static::assertSame($recoveredSecret, $recovered->getAppSecret());
            static::assertNull($recovered->getUnconfirmedAppSecrets());
            // The previous-signature proves the reinstall authenticated as the secret the app really holds.
            $this->assertConfirmCarriesDualSignature(
                $this->lastConfirm(),
                newSecret: $recoveredSecret,
                previousSecret: $adoptedSecret,
            );
            static::assertNull($deletedApps->getDeletedAppSecret(self::FIXTURE_APP_NAME));
        } finally {
            // Reinstalled through a standalone command, so the trait's #[After] cleanup does not track this row.
            $connection = static::getContainer()->get(Connection::class);
            $connection->executeStatement('DELETE FROM app WHERE name = :name', ['name' => self::FIXTURE_APP_NAME]);
            $connection->executeStatement('DELETE FROM deleted_apps WHERE name = :name', ['name' => self::FIXTURE_APP_NAME]);
        }
    }

    private function installApp(): AppEntity
    {
        $this->loadAppsFromDir(self::FIXTURE_APP_DIR);

        return $this->getInstalledApp();
    }

    private function createInstallCommand(): InstallAppCommand
    {
        return new InstallAppCommand(
            new AppLoader(\dirname(self::FIXTURE_APP_DIR), new NullLogger()),
            static::getContainer()->get(AppLifecycle::class),
            new AppPrinter($this->appRepository)
        );
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
            static::fail('An ambiguous rotation must surface as a failure.');
        } catch (AppException $e) {
            // expected — no candidate was accepted, the outcome is unknown and the operator must recover
            static::assertSame(AppException::APP_SECRET_RECOVERY_FAILED, $e->getErrorCode());
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
