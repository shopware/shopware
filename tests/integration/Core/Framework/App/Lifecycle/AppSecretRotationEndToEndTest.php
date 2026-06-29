<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
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

    public function testRotationReRegistersAndCommitsTheAppMintedSecret(): void
    {
        $app = $this->installApp();
        // The auto-responding app server commits its own secret during the initial registration.
        static::assertSame(TestAppServer::APP_SECRET, $app->getAppSecret());

        $firstRequest = $this->getRequestCount();
        $this->appendHandshake('rotated-secret');
        $this->appendNewResponse(new Response(200));

        $this->rotationService->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_API);

        $rotated = $this->getInstalledApp();
        static::assertSame('rotated-secret', $rotated->getAppSecret());
        static::assertNull($rotated->getUnconfirmedAppSecrets());

        // The confirm proves the app holds the new secret (the new signature) and proves we are the same shop
        // by also signing with the secret the app held before (the previous signature).
        $this->assertConfirmSignedWith($this->getPastRequest($firstRequest + 1), 'rotated-secret', TestAppServer::APP_SECRET);
    }

    public function testAmbiguousConfirmTimeoutLeavesAPendingSecretLikeAServerError(): void
    {
        $app = $this->installApp();
        $committedSecret = $app->getAppSecret();
        static::assertNotNull($committedSecret);

        // The confirm fails as a transport timeout — a ConnectException with NO response — rather than a 5xx
        // with a body. That hits a different arm of registrationFailedFromResponse (getResponse() === null),
        // but it must still be treated as an ambiguous outcome (the app may have switched), so the pending
        // secret is retained, not dropped the way a definitive 4xx rejection would be.
        $this->appendHandshake('pending-secret');
        $this->appendNewResponse(new ConnectException('Connection timed out', new Request('POST', TestAppServer::CONFIRMATION_URL)));

        try {
            $this->rotationService->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_API);
            static::fail('An ambiguous confirm timeout must surface as a registration failure.');
        } catch (AppRegistrationException) {
            // expected — the outcome is unknown, the operator must recover
        }

        $afterRotation = $this->getInstalledApp();
        static::assertSame($committedSecret, $afterRotation->getAppSecret(), 'a timeout must not commit the new secret');
        static::assertSame(['pending-secret'], $afterRotation->getUnconfirmedAppSecrets(), 'a timeout retains the pending secret, like a 5xx');
    }

    public function testAmbiguousRotationLeavesPendingThenRecoveryFallsBackToTheCurrentSecret(): void
    {
        $app = $this->installApp();
        $committedSecret = $app->getAppSecret();
        static::assertNotNull($committedSecret);

        // Rotation: the app gives us a new secret but the confirm fails without a clear answer (5xx), so we
        // cannot tell whether the app switched. The new secret is left pending; the active secret stays put.
        $rotationStart = $this->getRequestCount();
        $this->appendHandshake('pending-secret');
        $this->appendNewResponse(new Response(500));

        try {
            $this->rotationService->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_API);
            static::fail('An ambiguous rotation must surface as a registration failure.');
        } catch (AppRegistrationException) {
            // expected — the outcome is unknown, the operator must recover
        }

        $afterRotation = $this->getInstalledApp();
        static::assertSame($committedSecret, $afterRotation->getAppSecret());
        static::assertSame(['pending-secret'], $afterRotation->getUnconfirmedAppSecrets());
        $this->assertConfirmSignedWith($this->getPastRequest($rotationStart + 1), 'pending-secret', $committedSecret);

        // Recovery re-registers, signing with the pending secret first. Here the app never switched (it still
        // holds the old secret), so it rejects the pending one (4xx); recovery then falls back to the current
        // secret, which the app accepts.
        $recoveryStart = $this->getRequestCount();
        $this->appendHandshake('recovered-after-fallback');
        $this->appendNewResponse(new Response(403));
        $this->appendHandshake('recovered-after-fallback');
        $this->appendNewResponse(new Response(200));

        $this->rotationService->recoverNow($app->getId(), $this->context);

        $recovered = $this->getInstalledApp();
        static::assertSame('recovered-after-fallback', $recovered->getAppSecret());
        static::assertNull($recovered->getUnconfirmedAppSecrets());

        // First attempt authenticates as the pending secret; the fall-back authenticates as the committed one.
        static::assertSame(
            'pending-secret',
            $this->previousSignatureSecret($this->getPastRequest($recoveryStart + 1)),
            'recovery must try the pending secret first'
        );
        static::assertSame(
            $committedSecret,
            $this->previousSignatureSecret($this->getPastRequest($recoveryStart + 3)),
            'recovery must fall back to the committed secret'
        );
    }

    public function testRecoveryCommitsWithThePendingSecretWhenTheAppPromotedIt(): void
    {
        $app = $this->installApp();
        $committedSecret = $app->getAppSecret();
        static::assertNotNull($committedSecret);

        $this->appendHandshake('pending-secret');
        $this->appendNewResponse(new Response(500));
        try {
            $this->rotationService->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_API);
            static::fail('An ambiguous rotation must surface as a registration failure.');
        } catch (AppRegistrationException) {
        }

        static::assertSame(['pending-secret'], $this->getInstalledApp()->getUnconfirmedAppSecrets());

        // This time the app did switch to the pending secret, so it accepts the first recovery attempt.
        $recoveryStart = $this->getRequestCount();
        $this->appendHandshake('recovered-with-pending');
        $this->appendNewResponse(new Response(200));

        $this->rotationService->recoverNow($app->getId(), $this->context);

        $recovered = $this->getInstalledApp();
        static::assertSame('recovered-with-pending', $recovered->getAppSecret());
        static::assertNull($recovered->getUnconfirmedAppSecrets());

        // Only one recovery attempt happened (handshake + confirm), signed with the pending secret — it did
        // not need to fall back to the current secret.
        static::assertSame($recoveryStart + 2, $this->getRequestCount());
        $this->assertConfirmSignedWith($this->getPastRequest($recoveryStart + 1), 'recovered-with-pending', 'pending-secret');
    }

    public function testAmbiguousFirstInstallKeepsTheAppSoTheSecretCanBeRecovered(): void
    {
        // Drives the REAL install path (not a seeded row): a first install whose handshake succeeds — the app
        // mints and persists a secret — but whose confirm fails ambiguously (5xx). The app may already hold
        // that secret, and a confirmed app refuses a fresh re-registration, so deleting the row here (the old
        // behaviour) stranded the install permanently. The row and its pending secret must survive instead.
        $manifest = Manifest::createFromXmlFile(self::FIXTURE_APP_DIR . '/manifest.xml');

        $this->appendHandshake('first-install-secret');
        $this->appendNewResponse(new Response(500));

        try {
            static::getContainer()->get(AppLifecycle::class)->install($manifest, new AppInstallParameters(), $this->context);
            static::fail('An ambiguous first install must surface as a registration failure.');
        } catch (AppRegistrationException) {
            // expected — the confirm outcome is unknown
        }

        try {
            $halfInstalled = $this->getInstalledApp();
            // The row and the pending secret the app may hold survive — they are not deleted.
            static::assertNull($halfInstalled->getAppSecret(), 'a first install never commits a secret');
            static::assertSame(['first-install-secret'], $halfInstalled->getUnconfirmedAppSecrets());

            // Recovery re-registers against that pending secret (its only candidate) and commits a fresh one.
            $recoveryStart = $this->getRequestCount();
            $this->appendHandshake('recovered-secret');
            $this->appendNewResponse(new Response(200));
            $this->rotationService->recoverNow($halfInstalled->getId(), $this->context);

            $recovered = $this->getInstalledApp();
            static::assertSame('recovered-secret', $recovered->getAppSecret());
            static::assertNull($recovered->getUnconfirmedAppSecrets());
            // The confirm proves the new secret and authenticates as the pending secret the app held.
            $this->assertConfirmSignedWith($this->getPastRequest($recoveryStart + 1), 'recovered-secret', 'first-install-secret');
        } finally {
            // Installed via AppLifecycle directly, so the trait's #[After] cleanup does not track this app.
            static::getContainer()->get(Connection::class)->executeStatement(
                'DELETE FROM app WHERE name = :name',
                ['name' => self::FIXTURE_APP_NAME]
            );
        }
    }

    public function testRecoveryReRegistersAFirstRegistrationThatNeverCommitted(): void
    {
        $app = $this->installApp();
        static::assertNotNull($app->getAppSecret());

        // A first registration that minted a secret (the app persisted it at the handshake) but was killed
        // before committing leaves appSecret=null with a pending secret. This state cannot be produced by a
        // real rotation — which needs a committed secret — so seed it directly to mirror the crashed-install
        // row, then recover against it.
        $this->context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($app): void {
            $this->appRepository->update([[
                'id' => $app->getId(),
                'appSecret' => null,
                'unconfirmedAppSecrets' => ['app-held-secret'],
            ]], $context);
        });

        // With no committed secret there is no fallback candidate, so recovery makes a single attempt
        // (handshake + confirm) and must authenticate as the pending secret the app is presumed to hold.
        $recoveryStart = $this->getRequestCount();
        $this->appendHandshake('recovered-first-registration');
        $this->appendNewResponse(new Response(200));

        $this->rotationService->recoverNow($app->getId(), $this->context);

        $recovered = $this->getInstalledApp();
        static::assertSame('recovered-first-registration', $recovered->getAppSecret());
        static::assertNull($recovered->getUnconfirmedAppSecrets());

        // Exactly one re-registration attempt: the pending secret is the only candidate (no committed fallback).
        static::assertSame($recoveryStart + 2, $this->getRequestCount());
        $this->assertConfirmSignedWith($this->getPastRequest($recoveryStart + 1), 'recovered-first-registration', 'app-held-secret');
    }

    public function testRecoveryNotifiesWhenAFirstRegistrationsPendingSecretIsRejected(): void
    {
        $app = $this->installApp();

        // First-registration crash state again, but this time the app does not hold the pending secret.
        $this->context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($app): void {
            $this->appRepository->update([[
                'id' => $app->getId(),
                'appSecret' => null,
                'unconfirmedAppSecrets' => ['app-held-secret'],
            ]], $context);
        });

        // The app rejects the pending secret (4xx) and there is no committed secret to fall back to, so the
        // registration cannot be recovered programmatically — recovery notifies (claimed) for operator action
        // rather than silently failing or losing state.
        $this->appendHandshake('rejected');
        $this->appendNewResponse(new Response(403));

        try {
            $this->rotationService->recoverNow($app->getId(), $this->context);
            static::fail('Recovery must report the registration as claimed when the only candidate is rejected.');
        } catch (AppException $e) {
            static::assertSame(AppException::APP_SECRET_ROTATION_CLAIMED, $e->getErrorCode());
        }

        $reverted = $this->getInstalledApp();
        // No committed secret to restore, and the pending record is cleared by the revert.
        static::assertNull($reverted->getAppSecret());
        static::assertNull($reverted->getUnconfirmedAppSecrets());
    }

    public function testRecoveryRevertsAndRecommendsShopIdChangeWhenBothSecretsAreRejected(): void
    {
        $app = $this->installApp();
        $committedSecret = $app->getAppSecret();
        static::assertNotNull($committedSecret);

        $this->appendHandshake('pending-secret');
        $this->appendNewResponse(new Response(500));
        try {
            $this->rotationService->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_API);
            static::fail('An ambiguous rotation must surface as a registration failure.');
        } catch (AppRegistrationException) {
        }

        // Neither the pending nor the committed secret is accepted: core no longer holds a secret the app trusts.
        $this->appendHandshake('recovery-rejected');
        $this->appendNewResponse(new Response(403));
        $this->appendHandshake('recovery-rejected');
        $this->appendNewResponse(new Response(403));

        try {
            $this->rotationService->recoverNow($app->getId(), $this->context);
            static::fail('Recovery must report the registration as claimed when both secrets are rejected.');
        } catch (AppException $e) {
            static::assertSame(AppException::APP_SECRET_ROTATION_CLAIMED, $e->getErrorCode());
            static::assertStringContainsString('app:shop-id:change', $e->getMessage());
        }

        // The active secret is left on the last known-good value and the pending record is cleared.
        $reverted = $this->getInstalledApp();
        static::assertSame($committedSecret, $reverted->getAppSecret());
        static::assertNull($reverted->getUnconfirmedAppSecrets());
    }

    public function testRecoveryRollsBackTheIntegrationAndPreservesThePendingWhenAnAttemptFailsAmbiguously(): void
    {
        $app = $this->installApp();
        $committedSecret = $app->getAppSecret();
        static::assertNotNull($committedSecret);

        $this->appendHandshake('pending-secret');
        $this->appendNewResponse(new Response(500));
        try {
            $this->rotationService->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_API);
            static::fail('An ambiguous rotation must surface as a registration failure.');
        } catch (AppRegistrationException) {
        }

        $afterRotation = $this->getInstalledApp();
        static::assertSame(['pending-secret'], $afterRotation->getUnconfirmedAppSecrets());
        $integrationBeforeRecovery = $afterRotation->getIntegrationId();

        // Recovery's first attempt cannot even complete the handshake (a transport failure, not a rejection):
        // the outcome is unknown. No fresh credentials were delivered, so the integration switch is undone and
        // the recovery record is preserved for a retry rather than left broken.
        $this->appendNewResponse(new Response(500));
        try {
            $this->rotationService->recoverNow($app->getId(), $this->context);
            static::fail('An ambiguous recovery attempt must surface as a registration failure.');
        } catch (AppRegistrationException) {
        }

        $afterRecovery = $this->getInstalledApp();
        static::assertSame($committedSecret, $afterRecovery->getAppSecret());
        static::assertSame(['pending-secret'], $afterRecovery->getUnconfirmedAppSecrets());
        static::assertSame($integrationBeforeRecovery, $afterRecovery->getIntegrationId());
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

    private function appendHandshake(string $mintedSecret): void
    {
        $shopId = static::getContainer()->get(ShopIdProvider::class)->getShopId()->id;
        $proof = hash_hmac('sha256', $shopId . $this->shopUrl . self::FIXTURE_APP_NAME, TestAppServer::TEST_SETUP_SECRET);

        $this->appendNewResponse(new Response(200, [], json_encode([
            'proof' => $proof,
            'secret' => $mintedSecret,
            'confirmation_url' => TestAppServer::CONFIRMATION_URL,
        ], \JSON_THROW_ON_ERROR)));
    }

    private function assertConfirmSignedWith(RequestInterface $confirm, string $newSecret, string $previousSecret): void
    {
        static::assertSame('POST', $confirm->getMethod());

        $json = $this->confirmPayloadJson($confirm);
        static::assertSame(hash_hmac('sha256', $json, $newSecret), $confirm->getHeaderLine('shopware-shop-signature'));
        static::assertSame(hash_hmac('sha256', $json, $previousSecret), $confirm->getHeaderLine('shopware-shop-signature-previous'));
    }

    /**
     * Work out which secret produced the previous-signature header, by recomputing the signature for each
     * secret this test used and seeing which one matches. Returns the matching secret, or '' if neither does.
     */
    private function previousSignatureSecret(RequestInterface $confirm): string
    {
        $json = $this->confirmPayloadJson($confirm);
        $previousSignature = $confirm->getHeaderLine('shopware-shop-signature-previous');

        foreach (['pending-secret', TestAppServer::APP_SECRET] as $candidate) {
            if (hash_equals(hash_hmac('sha256', $json, $candidate), $previousSignature)) {
                return $candidate;
            }
        }

        return '';
    }

    private function confirmPayloadJson(RequestInterface $confirm): string
    {
        $payload = json_decode($confirm->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);

        return json_encode($payload, \JSON_THROW_ON_ERROR);
    }
}
