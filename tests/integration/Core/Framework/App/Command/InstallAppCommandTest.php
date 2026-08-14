<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Command;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Command\AppPrinter;
use Shopware\Core\Framework\App\Command\InstallAppCommand;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class InstallAppCommandTest extends TestCase
{
    use EventDispatcherBehaviour;
    use GuzzleTestClientBehaviour;

    private const RECOVERY_APP_DIR = __DIR__ . '/../Manifest/_fixtures/test';

    private const RECOVERY_APP_NAME = 'test';

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    private AppFixture $appFixture;

    private Context $context;

    protected function setUp(): void
    {
        $this->appRepository = static::getContainer()->get('app.repository');

        /** @var AppFixture $appFixture */
        $appFixture = static::getContainer()->get(AppFixture::class);
        $this->appFixture = $appFixture;
        $this->context = Context::createDefaultContext();

        static::getContainer()->get(ShopIdProvider::class)->getShopId();
    }

    public function testInstallWithoutPermissions(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->setInputs(['yes']);

        $commandTester->execute(['name' => 'withoutPermissions']);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());

        static::assertStringContainsString('[OK] App withoutPermissions has been successfully installed.', $commandTester->getDisplay());
    }

    public function testInstallWithForce(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));

        $commandTester->execute(['name' => 'withPermissions', '-f' => true]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());

        static::assertStringContainsString('[OK] App withPermissions has been successfully installed.', $commandTester->getDisplay());
    }

    public function testInstallWithPermissionsAndDomains(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->setInputs(['yes', 'yes']);

        $commandTester->execute(['name' => 'withPermissions']);

        static::assertSame(0, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        // header permissions
        static::assertMatchesRegularExpression('/.*Resource\s+Privileges\s+\n.*/', $display);
        // content permissions
        static::assertMatchesRegularExpression('/.*product\s+write, delete\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*category\s+write\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*order\s+read\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*user_change_me\s+\n.*/', $display);

        // header domains
        static::assertMatchesRegularExpression('/.*Domain\s+\n.*/', $display);
        // content domains
        static::assertMatchesRegularExpression('/.*my.app.com\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*swag-test.com\s+\n.*/', $display);

        static::assertStringContainsString('[OK] App withPermissions has been successfully installed.', $display);
    }

    public function testInstallWithAllowedHosts(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->setInputs(['yes', 'yes']);

        $commandTester->execute(['name' => 'withAllowedHosts']);

        static::assertSame(0, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        // header domain
        static::assertMatchesRegularExpression('/.*Domain\s+\n.*/', $display);
        // content domains
        static::assertMatchesRegularExpression('/.*shopware.com\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*example.com\s+\n.*/', $display);

        static::assertStringContainsString('[OK] App withAllowedHosts has been successfully installed.', $display);
    }

    public function testInstallWithPermissionsCancel(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->setInputs(['no']);

        $commandTester->execute(['name' => 'withPermissions']);

        static::assertSame(1, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        // header permissions
        static::assertMatchesRegularExpression('/.*Resource\s+Privileges\s+\n.*/', $display);
        // content permissions
        static::assertMatchesRegularExpression('/.*product\s+write, delete\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*category\s+write\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*order\s+read\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*user_change_me\s+\n.*/', $display);

        static::assertStringContainsString('Aborting due to user input.', $commandTester->getDisplay());
    }

    public function testInstallWithActivation(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->setInputs(['yes']);

        $commandTester->execute(['name' => 'withoutPermissions', '-a' => true]);

        static::assertSame(0, $commandTester->getStatusCode());

        static::assertStringContainsString('[OK] App withoutPermissions has been successfully installed.', $commandTester->getDisplay());
    }

    public function testInstallWithNotFoundApp(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));

        $commandTester->execute(['name' => 'Test']);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());

        static::assertStringContainsString('[INFO] Could not find any app with this name', $commandTester->getDisplay());
    }

    public function testInstallFailsIfAppIsAlreadyInstalled(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->setInputs(['yes']);

        $commandTester->execute(['name' => 'withoutPermissions']);
        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());

        $commandTester->execute(['name' => 'withoutPermissions']);
        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('[INFO] App withoutPermissions is already installed', $commandTester->getDisplay());
    }

    public function testInstallRecoversPendingCredentialsWithoutReplayingLifecycleOrActivation(): void
    {
        $app = $this->createRecoveryApp('current-secret', 'pending-secret');
        $this->appendRecoveryHandshake('recovered-secret');
        $this->appendNewResponse(new Response(200));

        $installedEvents = 0;
        $eventDispatcher = static::getContainer()->get('event_dispatcher');
        static::assertInstanceOf(EventDispatcherInterface::class, $eventDispatcher);
        $this->addEventListener($eventDispatcher, AppInstalledEvent::class, static function () use (&$installedEvents): void {
            ++$installedEvents;
        });

        $tester = $this->createRecoveryCommandTester();
        static::assertSame(Command::SUCCESS, $tester->execute([
            'name' => self::RECOVERY_APP_NAME,
            '-f' => true,
            '-a' => true,
        ]));

        $recovered = $this->appFixture->getApp($app->getId());
        static::assertSame('recovered-secret', $recovered->getAppSecret());
        static::assertNull($recovered->getUnconfirmedAppSecrets());
        static::assertFalse($recovered->isActive(), '--activate must not change an established app during repair');
        static::assertSame(0, $installedEvents, 'repair of an established app must not replay AppInstalledEvent');
    }

    public function testInstallRecoveryKeepsCandidatesWhenTheRetryIsAmbiguous(): void
    {
        $app = $this->createRecoveryApp('current-secret', 'pending-secret');
        $this->appendRecoveryHandshake('minted-recovery');
        $this->appendNewResponse(new Response(500));
        // The recovery walks on to the committed secret, whose handshake fails before anything is minted.
        $this->appendNewResponse(new Response(500));

        try {
            $this->createRecoveryCommandTester()->execute([
                'name' => self::RECOVERY_APP_NAME,
                '-f' => true,
            ]);
            static::fail('An ambiguous recovery must surface so app:install can be retried.');
        } catch (AppException $e) {
            static::assertSame(AppException::APP_SECRET_RECOVERY_FAILED, $e->getErrorCode());
        }

        $pending = $this->appFixture->getApp($app->getId());
        static::assertSame('current-secret', $pending->getAppSecret());
        static::assertSame(['minted-recovery', 'pending-secret'], $pending->getUnconfirmedAppSecrets());
    }

    public function testInstallFailsIfAppHasValidations(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/../Manifest/_fixtures'));
        $commandTester->setInputs(['yes', 'yes']);
        $commandTester->execute(['name' => 'invalidWebhooks']);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('App installation of invalidWebhooks failed due: ', $commandTester->getDisplay());
    }

    public function testABlockingValidationErrorIsReportedAndFailsTheCommand(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/../Manifest/_fixtures'));
        $commandTester->setInputs(['yes', 'yes']);

        // The non-hookable tax.written webhook is only reported; the missing permissions refuse.
        $commandTester->execute(['name' => 'invalidWebhooks']);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('App installation of invalidWebhooks failed due', $commandTester->getDisplay());
        static::assertStringContainsString('order:read', $commandTester->getDisplay());
    }

    public function testAnAdvisoryValidationErrorDoesNotStopAnInstall(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->setInputs(['yes', 'yes']);
        $commandTester->execute(['name' => 'withoutPermissions']);

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertStringContainsString('App withoutPermissions has been successfully installed.', $commandTester->getDisplay());
    }

    public function testInstallMultipleAppsAtOnceForced(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->setInputs(['yes']);

        $commandTester->execute(['name' => ['withoutPermissions', 'withPermissions'], '-a' => true, '-f' => true]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());

        static::assertStringContainsString('[OK] App withoutPermissions has been successfully installed.', $commandTester->getDisplay());
        static::assertStringContainsString('[OK] App withPermissions has been successfully installed.', $commandTester->getDisplay());
    }

    private function createRecoveryCommandTester(): CommandTester
    {
        return new CommandTester($this->createCommand(\dirname(self::RECOVERY_APP_DIR)));
    }

    private function appendRecoveryHandshake(string $appSecret): void
    {
        $manifest = Manifest::createFromXmlFile(self::RECOVERY_APP_DIR . '/manifest.xml');
        $setup = $manifest->getSetup();
        static::assertNotNull($setup);
        $secret = $setup->getSecret();
        static::assertNotNull($secret);

        $shopId = static::getContainer()->get(ShopIdProvider::class)->getShopId();
        $proof = hash_hmac(
            'sha256',
            $shopId . $_SERVER['APP_URL'] . $manifest->getMetadata()->getName(),
            $secret
        );

        $this->appendNewResponse(new Response(200, [], json_encode([
            'proof' => $proof,
            'secret' => $appSecret,
            'confirmation_url' => 'https://example.com/confirm',
        ], \JSON_THROW_ON_ERROR)));
    }

    private function createRecoveryApp(string $appSecret, string $pendingSecret): AppEntity
    {
        $app = $this->appFixture->createApp(
            $this->appFixture->loadManifest(self::RECOVERY_APP_DIR . '/manifest.xml'),
            $appSecret,
        );

        $this->context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($app, $appSecret, $pendingSecret): void {
            $this->appRepository->update([[
                'id' => $app->getId(),
                'appSecret' => $appSecret,
                'unconfirmedAppSecrets' => [$pendingSecret],
                'active' => false,
            ]], $context);
        });

        return $app;
    }

    private function createCommand(string $appFolder): InstallAppCommand
    {
        return new InstallAppCommand(
            new AppLoader($appFolder, new NullLogger()),
            static::getContainer()->get(AppLifecycle::class),
            new AppPrinter($this->appRepository)
        );
    }
}
