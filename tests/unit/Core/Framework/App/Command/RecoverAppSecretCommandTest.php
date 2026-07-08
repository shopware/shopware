<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Command\RecoverAppSecretCommand;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRecoveryResult;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(RecoverAppSecretCommand::class)]
class RecoverAppSecretCommandTest extends TestCase
{
    public function testListReportsWhenNoAppHasAPendingSecret(): void
    {
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->method('findAppsWithUnconfirmedSecrets')->willReturn(new AppCollection([]));

        $tester = $this->tester(AppFixture::createAppRepository(), $rotationService);

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        static::assertStringContainsString('No apps have an unconfirmed secret.', $tester->getDisplay());
    }

    public function testListShowsAppsWithAPendingSecret(): void
    {
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $app = $this->app('StuckApp');
        $app->setUnconfirmedAppSecretsUpdatedAt(new \DateTimeImmutable('2025-06-13 12:00:00+00:00'));
        $rotationService->method('findAppsWithUnconfirmedSecrets')->willReturn(new AppCollection([$app]));

        $tester = $this->tester(AppFixture::createAppRepository(), $rotationService);

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        static::assertStringContainsString('Pending since', $display);
        static::assertStringContainsString('StuckApp', $display);
        static::assertStringContainsString('2025-06-13T12:00:00+00:00', $display);
    }

    public function testRecoverFailsWhenTheAppDoesNotExist(): void
    {
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->never())->method('recoverNow');

        $tester = $this->tester(AppFixture::createAppRepository(), $rotationService);

        static::assertSame(Command::FAILURE, $tester->execute(['name' => 'Ghost']));
        static::assertStringContainsString('No app found for "Ghost".', $tester->getDisplay());
    }

    public function testRecoverSucceeds(): void
    {
        $app = $this->app('Recoverable');

        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->once())
            ->method('recoverNow')
            ->with($app->getId(), static::isInstanceOf(Context::class))
            ->willReturn(AppSecretRecoveryResult::Recovered);

        $tester = $this->tester(AppFixture::createAppRepository($app), $rotationService);

        static::assertSame(Command::SUCCESS, $tester->execute(['name' => 'Recoverable']));
        static::assertStringContainsString('Re-registered app "Recoverable" with a fresh secret.', $tester->getDisplay());
    }

    public function testDiscardSucceeds(): void
    {
        $app = $this->app('Lost');

        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->once())
            ->method('discardNow')
            ->with($app->getId(), static::isInstanceOf(Context::class));
        $rotationService->expects($this->never())->method('recoverNow');

        $tester = $this->tester(AppFixture::createAppRepository($app), $rotationService);

        static::assertSame(Command::SUCCESS, $tester->execute(['name' => 'Lost', '--discard' => true]));
        static::assertStringContainsString('app:shop-id:change', $tester->getDisplay());
    }

    public function testDiscardWithoutAnAppNameFails(): void
    {
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->never())->method('discardNow');

        $tester = $this->tester(AppFixture::createAppRepository(), $rotationService);

        static::assertSame(Command::FAILURE, $tester->execute(['--discard' => true]));
        static::assertStringContainsString('--discard requires an app name.', $tester->getDisplay());
    }

    public function testRecoverReportsSuccessWhenThereIsNothingToRecover(): void
    {
        [$exitCode, $display] = $this->runRecoverWithResult('Clean', AppSecretRecoveryResult::NothingToRecover);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('no unconfirmed secret to recover', $display);
    }

    public function testRecoverFailsWhenThePendingSecretWasAlreadyClaimed(): void
    {
        [$exitCode, $display] = $this->runRecoverWithResult('Taken', AppSecretRecoveryResult::Claimed);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('--discard', $display);
        static::assertStringContainsString('app:shop-id:change', $display);
    }

    public function testRecoverReportsUnknownOutcomeWhenTheAppDidNotConfirm(): void
    {
        [$exitCode, $display] = $this->runRecoverWithFailure('Flaky', AppException::registrationFailed('Flaky', 'connection timed out'));

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('unknown', $display);
    }

    /**
     * The command is invokable; CommandTester wraps it in a Command bound to the #[Argument]/#[Option]
     * attributes, so tests exercise the real console input definition instead of calling __invoke directly.
     *
     * @param EntityRepository<AppCollection> $appRepository
     */
    private function tester(EntityRepository $appRepository, AppSecretRotationService $rotationService): CommandTester
    {
        return new CommandTester(new RecoverAppSecretCommand($appRepository, $rotationService));
    }

    /**
     * Runs the recover path for an app named $name whose recoverNow() returns $result.
     *
     * @return array{int, string}
     */
    private function runRecoverWithResult(string $name, AppSecretRecoveryResult $result): array
    {
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->method('recoverNow')->willReturn($result);

        $tester = $this->tester(AppFixture::createAppRepository($this->app($name)), $rotationService);

        return [$tester->execute(['name' => $name]), $tester->getDisplay()];
    }

    /**
     * Runs the recover path for an app named $name whose recoverNow() throws $failure.
     *
     * @return array{int, string}
     */
    private function runRecoverWithFailure(string $name, AppException $failure): array
    {
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->method('recoverNow')->willThrowException($failure);

        $tester = $this->tester(AppFixture::createAppRepository($this->app($name)), $rotationService);

        return [$tester->execute(['name' => $name]), $tester->getDisplay()];
    }

    private function app(string $name): AppEntity
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName($name);

        return $app;
    }
}
