<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Command\RecoverAppSecretsCommand;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\RetainedSecret\AppRetainedSecretGateway;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RecoverAppSecretsCommand::class)]
class RecoverAppSecretsCommandTest extends TestCase
{
    public function testListPrintsEverySecretWithItsShopIdAndExpiry(): void
    {
        $tester = new CommandTester($this->createCommand([$this->app('TestApp')], $this->keptRows('TestApp')));

        $tester->execute(['name' => 'TestApp', '--list' => true]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        static::assertStringContainsString('contains live app secrets', $display);
        static::assertStringContainsString('current-secret', $display);
        static::assertStringContainsString('kept-newer', $display);
        static::assertStringContainsString('old-shop', $display);
        static::assertStringContainsString('2026-01-04T12:00:00+00:00', $display);
    }

    public function testStartPrintsOnlyTheKeptSecretsWhenTheCopyKeptAny(): void
    {
        $tester = new CommandTester($this->createCommand([$this->app('TestApp')], $this->keptRows('TestApp')));

        $tester->execute(['name' => 'TestApp', '--start' => true]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertSame([
            ['app' => 'TestApp', 'secret' => 'kept-newer'],
            ['app' => 'TestApp', 'secret' => 'kept-older'],
        ], json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testStartPrintsTheCurrentSecretsWhenNothingWasKept(): void
    {
        $tester = new CommandTester($this->createCommand([$this->app('TestApp')], []));

        $tester->execute(['name' => 'TestApp', '--start' => true]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertSame([
            ['app' => 'TestApp', 'secret' => 'pending-secret'],
            ['app' => 'TestApp', 'secret' => 'current-secret'],
        ], json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testStartWithAllPrintsTheSecretsOfEveryApp(): void
    {
        $tester = new CommandTester($this->createCommand([], [...$this->keptRows('FirstApp'), ...$this->keptRows('SecondApp')]));

        $tester->execute(['--start' => true, '--all' => true]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertSame([
            ['app' => 'FirstApp', 'secret' => 'kept-newer'],
            ['app' => 'FirstApp', 'secret' => 'kept-older'],
            ['app' => 'SecondApp', 'secret' => 'kept-newer'],
            ['app' => 'SecondApp', 'secret' => 'kept-older'],
        ], json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testFailsWhenNoSecretIsFound(): void
    {
        $tester = new CommandTester($this->createCommand([], []));

        $tester->execute(['name' => 'TestApp', '--list' => true]);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('No secrets found', $tester->getDisplay());
    }

    public function testNeedsExactlyOneStep(): void
    {
        $tester = new CommandTester($this->createCommand([], []));

        $tester->execute(['name' => 'TestApp', '--list' => true, '--start' => true]);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('exactly one of --list, --start or --finish', $tester->getDisplay());
    }

    public function testStartNeedsAnAppNameOrAll(): void
    {
        $tester = new CommandTester($this->createCommand([], []));

        $tester->execute(['--start' => true]);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('Pass an app name or --all', $tester->getDisplay());
    }

    public function testFinishRegistersEachAppAgainWithItsSecretsInOrder(): void
    {
        $firstApp = $this->app('FirstApp');
        $secondApp = $this->app('SecondApp');

        $calls = [];
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->exactly(2))
            ->method('rotateNow')
            ->willReturnCallback(static function (string $appId, Context $context, string $trigger, array $secrets) use (&$calls): void {
                $calls[] = [$appId, $trigger, $secrets];
            });

        $tester = new CommandTester($this->createCommand([$firstApp, $secondApp], [], $rotationService));
        $tester->setInputs([json_encode([
            ['app' => 'FirstApp', 'secret' => 'secret-newer'],
            ['app' => 'FirstApp', 'secret' => 'secret-older'],
            ['app' => 'SecondApp', 'secret' => 'secret-only'],
        ], \JSON_THROW_ON_ERROR)]);

        $tester->execute(['--finish' => true]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertSame([
            [$firstApp->getId(), AppSecretRotationService::TRIGGER_CLI, ['secret-newer', 'secret-older']],
            [$secondApp->getId(), AppSecretRotationService::TRIGGER_CLI, ['secret-only']],
        ], $calls);
    }

    public function testFinishRefusesInputThatIsNotTheHandoverJson(): void
    {
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->never())->method('rotateNow');

        $tester = new CommandTester($this->createCommand([], [], $rotationService));
        $tester->setInputs(['[{"app": "TestApp"}]']);

        $tester->execute(['--finish' => true]);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('must be the JSON printed by --start', $tester->getDisplay());
    }

    public function testFinishReportsAnAppThatIsNotInstalled(): void
    {
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->never())->method('rotateNow');

        $tester = new CommandTester($this->createCommand([], [], $rotationService));
        $tester->setInputs(['[{"app": "MissingApp", "secret": "some-secret"}]']);

        $tester->execute(['--finish' => true]);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('App MissingApp is not installed on this shop', $tester->getDisplay());
    }

    /**
     * @param list<AppEntity> $apps
     * @param list<array{appName: string, shopId: string, secret: string, expiresAt: \DateTimeImmutable}> $kept
     */
    private function createCommand(array $apps, array $kept, ?AppSecretRotationService $rotationService = null): RecoverAppSecretsCommand
    {
        $gateway = static::createStub(AppRetainedSecretGateway::class);
        $gateway->method('findValid')->willReturn($kept);

        return new RecoverAppSecretsCommand(
            new StaticEntityRepository([new AppCollection($apps)]),
            $gateway,
            $rotationService ?? static::createStub(AppSecretRotationService::class),
        );
    }

    private function app(string $name): AppEntity
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName($name);
        $app->setAppSecret('current-secret');
        $app->setUnconfirmedAppSecrets(['pending-secret']);

        return $app;
    }

    /**
     * @return list<array{appName: string, shopId: string, secret: string, expiresAt: \DateTimeImmutable}>
     */
    private function keptRows(string $appName): array
    {
        return [
            ['appName' => $appName, 'shopId' => 'old-shop', 'secret' => 'kept-newer', 'expiresAt' => new \DateTimeImmutable('2026-01-04 12:00:00', new \DateTimeZone('UTC'))],
            ['appName' => $appName, 'shopId' => 'old-shop', 'secret' => 'kept-older', 'expiresAt' => new \DateTimeImmutable('2026-01-03 12:00:00', new \DateTimeZone('UTC'))],
        ];
    }
}
