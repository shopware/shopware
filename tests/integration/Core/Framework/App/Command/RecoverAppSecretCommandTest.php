<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Command;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Command\RecoverAppSecretCommand;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
class RecoverAppSecretCommandTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private const FIXTURE_APP_DIR = __DIR__ . '/../Manifest/_fixtures/test';
    private const FIXTURE_APP_NAME = 'test';
    private const SECOND_APP_DIR = __DIR__ . '/../Manifest/_fixtures/minimal';
    private const SECOND_APP_NAME = 'minimal';

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    private AppFixture $appFixture;

    private Context $context;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->appRepository = static::getContainer()->get('app.repository');

        /** @var AppFixture $appFixture */
        $appFixture = static::getContainer()->get(AppFixture::class);
        $this->appFixture = $appFixture;

        $this->context = Context::createDefaultContext();
        $this->commandTester = new CommandTester(static::getContainer()->get(RecoverAppSecretCommand::class));

        // Reconcile the shop id to this environment before any app holds a secret, exactly as a real install
        // does. Once an app is registered, a fingerprint mismatch is refused rather than reconciled silently,
        // so the fixture apps below must be created against an already-settled shop id.
        static::getContainer()->get(ShopIdProvider::class)->getShopId();
    }

    public function testListShowsOnlyAppsWithPendingSecret(): void
    {
        $this->createTestApp('old-secret', 'pending-secret');
        $this->appFixture->createApp($this->appFixture->loadManifest(self::SECOND_APP_DIR . '/manifest.xml'), 'old-secret');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([]));

        $display = $this->commandTester->getDisplay();
        static::assertStringContainsString(self::FIXTURE_APP_NAME, $display);
        static::assertStringNotContainsString(self::SECOND_APP_NAME, $display);
    }

    public function testRecoverReReregistersWithThePendingSecretAndCommitsAFreshSecret(): void
    {
        $app = $this->createTestApp('current-secret', 'pending-secret');

        // The app accepts the first re-registration, signed with the pending secret.
        $this->appendHandshake('recovered-secret');
        $this->appendNewResponse(new Response(200, []));

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['name' => self::FIXTURE_APP_NAME]));
        static::assertStringContainsString('Re-registered', $this->commandTester->getDisplay());

        $recovered = $this->appFixture->getApp($app->getId());
        static::assertSame('recovered-secret', $recovered->getAppSecret());
        static::assertNull($recovered->getUnconfirmedAppSecrets());
    }

    public function testRecoverFallsBackToTheCurrentSecretWhenThePendingOneIsRejected(): void
    {
        $app = $this->createTestApp('current-secret', 'pending-secret');

        // First attempt (pending secret): the app's confirm definitively rejects it (4xx).
        $this->appendHandshake('minted-from-pending');
        $this->appendNewResponse(new Response(403, []));
        // Second attempt (current secret): accepted.
        $this->appendHandshake('minted-from-current');
        $this->appendNewResponse(new Response(200, []));

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['name' => self::FIXTURE_APP_NAME]));

        // The committed secret is the one minted on the accepted (second) attempt, not the rejected first.
        $recovered = $this->appFixture->getApp($app->getId());
        static::assertSame('minted-from-current', $recovered->getAppSecret());
        static::assertNull($recovered->getUnconfirmedAppSecrets());
    }

    public function testRecoverLeavesAFreshPendingSecretAndFailsWhenTheConfirmIsAmbiguous(): void
    {
        $app = $this->createTestApp('current-secret', 'pending-secret');

        // The app accepts the handshake (signed with the pending secret) but the confirm fails without a
        // clear answer (5xx/timeout). Recovery cannot tell whether the app switched, so it leaves the
        // newly created secret pending and reports a failure for the operator to retry, instead of
        // deciding the registration can no longer be recovered.
        $this->appendHandshake('minted-recovery');
        $this->appendNewResponse(new Response(500, []));

        static::assertSame(Command::FAILURE, $this->commandTester->execute(['name' => self::FIXTURE_APP_NAME]));
        static::assertStringContainsString('unknown', $this->commandTester->getDisplay());

        $afterAmbiguous = $this->appFixture->getApp($app->getId());
        // The active secret has not advanced. The freshly re-registered secret heads the pending list, with the
        // original pending secret kept behind it — a later retry still has every secret the app might hold.
        static::assertSame('current-secret', $afterAmbiguous->getAppSecret());
        static::assertSame(['minted-recovery', 'pending-secret'], $afterAmbiguous->getUnconfirmedAppSecrets());
    }

    public function testRecoverFailsAndPointsAtShopIdChangeWhenBothSecretsAreRejected(): void
    {
        $app = $this->createTestApp('current-secret', 'pending-secret');

        // Both attempts are definitively rejected (4xx) at the confirm step.
        $this->appendHandshake('minted-from-pending');
        $this->appendNewResponse(new Response(403, []));
        $this->appendHandshake('minted-from-current');
        $this->appendNewResponse(new Response(403, []));

        static::assertSame(Command::FAILURE, $this->commandTester->execute(['name' => self::FIXTURE_APP_NAME]));
        static::assertStringContainsString('app:shop-id:change', $this->commandTester->getDisplay());

        $reverted = $this->appFixture->getApp($app->getId());
        static::assertSame('current-secret', $reverted->getAppSecret());
        static::assertSame(['pending-secret'], $reverted->getUnconfirmedAppSecrets());
    }

    public function testDiscardClearsPendingSecretForShopIdChange(): void
    {
        $app = $this->createTestApp('current-secret', 'pending-secret');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['name' => self::FIXTURE_APP_NAME, '--discard' => true]));
        static::assertStringContainsString('app:shop-id:change', $this->commandTester->getDisplay());

        $discarded = $this->appFixture->getApp($app->getId());
        static::assertSame('current-secret', $discarded->getAppSecret());
        static::assertNull($discarded->getUnconfirmedAppSecrets());
    }

    public function testRecoverOnAppWithoutPendingSecretSucceedsWithNothingToDo(): void
    {
        $app = $this->createTestApp('current-secret', null);
        $requestsBefore = $this->getRequestCount();

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['name' => self::FIXTURE_APP_NAME]));
        static::assertStringContainsString('no unconfirmed secret', $this->commandTester->getDisplay());
        // it must not have touched the app server
        static::assertSame($requestsBefore, $this->getRequestCount());

        $unchanged = $this->appFixture->getApp($app->getId());
        static::assertSame('current-secret', $unchanged->getAppSecret());
        static::assertNull($unchanged->getUnconfirmedAppSecrets());
    }

    private function appendHandshake(string $appSecret): void
    {
        $manifest = Manifest::createFromXmlFile(self::FIXTURE_APP_DIR . '/manifest.xml');
        $setup = $manifest->getSetup();
        static::assertNotNull($setup);
        $secret = $setup->getSecret();
        static::assertNotNull($secret);

        $shopId = static::getContainer()->get(ShopIdProvider::class)->getShopId();
        $appName = $manifest->getMetadata()->getName();
        $proof = hash_hmac('sha256', $shopId . $_SERVER['APP_URL'] . $appName, $secret);

        $this->appendNewResponse(new Response(200, [], json_encode([
            'proof' => $proof,
            'secret' => $appSecret,
            'confirmation_url' => 'https://example.com/confirm',
        ], \JSON_THROW_ON_ERROR)));
    }

    /**
     * Persists the fixture app (no HTTP install) with a committed secret and, optionally, a pending secret,
     * so the recover command can be driven against it.
     */
    private function createTestApp(string $appSecret, ?string $pendingSecret): AppEntity
    {
        $app = $this->appFixture->createApp(
            $this->appFixture->loadManifest(self::FIXTURE_APP_DIR . '/manifest.xml'),
            $appSecret,
        );
        $this->seedSecrets($app->getId(), $appSecret, $pendingSecret);

        return $app;
    }

    private function seedSecrets(string $appId, string $appSecret, ?string $pendingSecret): void
    {
        $this->context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($appId, $appSecret, $pendingSecret): void {
            $this->appRepository->update([[
                'id' => $appId,
                'appSecret' => $appSecret,
                'unconfirmedAppSecrets' => $pendingSecret === null ? null : [$pendingSecret],
            ]], $context);
        });
    }
}
