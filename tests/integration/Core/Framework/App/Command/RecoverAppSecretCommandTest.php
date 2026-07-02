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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
class RecoverAppSecretCommandTest extends TestCase
{
    use AppSystemTestBehaviour;
    use GuzzleTestClientBehaviour;

    private const FIXTURE_APP_DIR = __DIR__ . '/../Manifest/_fixtures/test';
    private const FIXTURE_APP_NAME = 'test';

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    private Context $context;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->appRepository = static::getContainer()->get('app.repository');
        $this->context = Context::createDefaultContext();
        $this->commandTester = new CommandTester(static::getContainer()->get(RecoverAppSecretCommand::class));
    }

    public function testListShowsOnlyAppsWithPendingSecret(): void
    {
        $this->createPlainApp('WithPending', 'old-secret', 'pending-secret');
        $this->createPlainApp('WithoutPending', 'old-secret', null);

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([]));

        $display = $this->commandTester->getDisplay();
        static::assertStringContainsString('WithPending', $display);
        static::assertStringNotContainsString('WithoutPending', $display);
    }

    public function testRecoverReReregistersWithThePendingSecretAndCommitsAFreshSecret(): void
    {
        $this->loadAppsFromDir(self::FIXTURE_APP_DIR);
        $app = $this->getInstalledApp();
        $this->seedSecrets($app->getId(), 'current-secret', 'pending-secret');

        // The app accepts the first re-registration, signed with the pending secret.
        $this->appendHandshake('recovered-secret');
        $this->appendNewResponse(new Response(200, []));

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['name' => self::FIXTURE_APP_NAME]));
        static::assertStringContainsString('Re-registered', $this->commandTester->getDisplay());

        $recovered = $this->getInstalledApp();
        static::assertSame('recovered-secret', $recovered->getAppSecret());
        static::assertNull($recovered->getUnconfirmedAppSecrets());
    }

    public function testRecoverFallsBackToTheCurrentSecretWhenThePendingOneIsRejected(): void
    {
        $this->loadAppsFromDir(self::FIXTURE_APP_DIR);
        $app = $this->getInstalledApp();
        $this->seedSecrets($app->getId(), 'current-secret', 'pending-secret');

        // First attempt (pending secret): the app's confirm definitively rejects it (4xx).
        $this->appendHandshake('recovered-secret');
        $this->appendNewResponse(new Response(403, []));
        // Second attempt (current secret): accepted.
        $this->appendHandshake('recovered-secret');
        $this->appendNewResponse(new Response(200, []));

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['name' => self::FIXTURE_APP_NAME]));

        $recovered = $this->getInstalledApp();
        static::assertSame('recovered-secret', $recovered->getAppSecret());
        static::assertNull($recovered->getUnconfirmedAppSecrets());
    }

    public function testRecoverLeavesAFreshPendingSecretAndFailsWhenTheConfirmIsAmbiguous(): void
    {
        $this->loadAppsFromDir(self::FIXTURE_APP_DIR);
        $app = $this->getInstalledApp();
        $this->seedSecrets($app->getId(), 'current-secret', 'pending-secret');

        // The app accepts the handshake (signed with the pending secret) but the confirm fails without a
        // clear answer (5xx/timeout). Recovery cannot tell whether the app switched, so it leaves the
        // newly created secret pending and reports a failure for the operator to retry, instead of
        // deciding the registration can no longer be recovered.
        $this->appendHandshake('recovered-secret');
        $this->appendNewResponse(new Response(500, []));

        static::assertSame(Command::FAILURE, $this->commandTester->execute(['name' => self::FIXTURE_APP_NAME]));
        static::assertStringContainsString('unknown', $this->commandTester->getDisplay());

        $afterAmbiguous = $this->getInstalledApp();
        // The active secret has not advanced. The freshly re-registered secret heads the pending list, with the
        // original pending secret kept behind it — a later retry still has every secret the app might hold.
        static::assertSame('current-secret', $afterAmbiguous->getAppSecret());
        static::assertSame(['recovered-secret', 'pending-secret'], $afterAmbiguous->getUnconfirmedAppSecrets());
    }

    public function testRecoverFailsAndPointsAtShopIdChangeWhenBothSecretsAreRejected(): void
    {
        $this->loadAppsFromDir(self::FIXTURE_APP_DIR);
        $app = $this->getInstalledApp();
        $this->seedSecrets($app->getId(), 'current-secret', 'pending-secret');

        // Both attempts are definitively rejected (4xx) at the confirm step.
        $this->appendHandshake('recovered-secret');
        $this->appendNewResponse(new Response(403, []));
        $this->appendHandshake('recovered-secret');
        $this->appendNewResponse(new Response(403, []));

        static::assertSame(Command::FAILURE, $this->commandTester->execute(['name' => self::FIXTURE_APP_NAME]));
        static::assertStringContainsString('app:shop-id:change', $this->commandTester->getDisplay());

        $reverted = $this->getInstalledApp();
        static::assertSame('current-secret', $reverted->getAppSecret());
        static::assertSame(['pending-secret'], $reverted->getUnconfirmedAppSecrets());
    }

    public function testDiscardClearsPendingSecretForShopIdChange(): void
    {
        $this->loadAppsFromDir(self::FIXTURE_APP_DIR);
        $app = $this->getInstalledApp();
        $this->seedSecrets($app->getId(), 'current-secret', 'pending-secret');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['name' => self::FIXTURE_APP_NAME, '--discard' => true]));
        static::assertStringContainsString('app:shop-id:change', $this->commandTester->getDisplay());

        $discarded = $this->getInstalledApp();
        static::assertSame('current-secret', $discarded->getAppSecret());
        static::assertNull($discarded->getUnconfirmedAppSecrets());
    }

    public function testRecoverOnAppWithoutPendingSecretSucceedsWithNothingToDo(): void
    {
        $this->loadAppsFromDir(self::FIXTURE_APP_DIR);
        $app = $this->getInstalledApp();
        $this->seedSecrets($app->getId(), 'current-secret', null);
        $requestsAfterInstall = $this->getRequestCount();

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['name' => self::FIXTURE_APP_NAME]));
        static::assertStringContainsString('no unconfirmed secret', $this->commandTester->getDisplay());
        // it must not have touched the app server
        static::assertSame($requestsAfterInstall, $this->getRequestCount());

        $unchanged = $this->getInstalledApp();
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

    private function createPlainApp(string $name, string $appSecret, ?string $pendingSecret): string
    {
        $id = Uuid::randomHex();

        $this->appRepository->create([[
            'id' => $id,
            'name' => $name,
            'path' => __DIR__,
            'version' => '0.0.1',
            'label' => $name,
            'accessToken' => 'token',
            'integration' => [
                'label' => $name,
                'accessKey' => 'access-' . $id,
                'secretAccessKey' => 'secret',
            ],
            'aclRole' => [
                'id' => Uuid::randomHex(),
                'name' => $name,
            ],
        ]], $this->context);

        $this->seedSecrets($id, $appSecret, $pendingSecret);

        return $id;
    }

    private function getInstalledApp(): AppEntity
    {
        $criteria = new Criteria();
        $criteria->addAssociation('integration');

        $app = $this->appRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(AppEntity::class, $app);

        return $app;
    }
}
