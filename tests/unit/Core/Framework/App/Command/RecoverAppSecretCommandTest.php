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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

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

        [$io, $output] = $this->io();
        $exitCode = (new RecoverAppSecretCommand($this->createMock(EntityRepository::class), $rotationService))($io);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('No apps have an unconfirmed secret.', $output->fetch());
    }

    public function testListShowsAppsWithAPendingSecret(): void
    {
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $app = $this->app('StuckApp');
        $app->setUnconfirmedAppSecretsUpdatedAt(new \DateTimeImmutable('2025-06-13 12:00:00+00:00'));
        $rotationService->method('findAppsWithUnconfirmedSecrets')->willReturn(new AppCollection([$app]));

        [$io, $output] = $this->io();
        $exitCode = (new RecoverAppSecretCommand($this->createMock(EntityRepository::class), $rotationService))($io);

        static::assertSame(Command::SUCCESS, $exitCode);
        $content = $output->fetch();
        static::assertStringContainsString('Pending since', $content);
        static::assertStringContainsString('StuckApp', $content);
        static::assertStringContainsString('2025-06-13T12:00:00+00:00', $content);
    }

    public function testRecoverFailsWhenTheAppDoesNotExist(): void
    {
        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository->method('search')->willReturn($this->searchResult([]));

        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->never())->method('recoverNow');

        [$io, $output] = $this->io();
        $exitCode = (new RecoverAppSecretCommand($appRepository, $rotationService))($io, 'Ghost');

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('No app found for "Ghost".', $output->fetch());
    }

    public function testRecoverSucceeds(): void
    {
        $app = $this->app('Recoverable');

        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository->method('search')->willReturn($this->searchResult([$app]));

        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->once())
            ->method('recoverNow')
            ->with($app->getId(), static::isInstanceOf(Context::class))
            ->willReturn(AppSecretRecoveryResult::Recovered);

        [$io, $output] = $this->io();
        $exitCode = (new RecoverAppSecretCommand($appRepository, $rotationService))($io, 'Recoverable');

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('Re-registered app "Recoverable" with a fresh secret.', $output->fetch());
    }

    public function testDiscardSucceeds(): void
    {
        $app = $this->app('Lost');

        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository->method('search')->willReturn($this->searchResult([$app]));

        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->once())
            ->method('discardNow')
            ->with($app->getId(), static::isInstanceOf(Context::class));
        $rotationService->expects($this->never())->method('recoverNow');

        [$io, $output] = $this->io();
        $exitCode = (new RecoverAppSecretCommand($appRepository, $rotationService))($io, 'Lost', true);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('app:shop-id:change', $output->fetch());
    }

    public function testRecoverReportsSuccessWhenThereIsNothingToRecover(): void
    {
        [$exitCode, $output] = $this->runRecoverWithResult('Clean', AppSecretRecoveryResult::NothingToRecover);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('no unconfirmed secret to recover', $output->fetch());
    }

    public function testRecoverFailsWhenThePendingSecretWasAlreadyClaimed(): void
    {
        [$exitCode, $output] = $this->runRecoverWithResult('Taken', AppSecretRecoveryResult::Claimed);

        static::assertSame(Command::FAILURE, $exitCode);
        $content = $output->fetch();
        static::assertStringContainsString('--discard', $content);
        static::assertStringContainsString('app:shop-id:change', $content);
    }

    public function testRecoverReportsUnknownOutcomeWhenTheAppDidNotConfirm(): void
    {
        [$exitCode, $output] = $this->runRecoverWithFailure('Flaky', AppException::registrationFailed('Flaky', 'connection timed out'));

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('unknown', $output->fetch());
    }

    /**
     * Runs the recover path for an app named $name whose recoverNow() returns $result.
     *
     * @return array{int, BufferedOutput}
     */
    private function runRecoverWithResult(string $name, AppSecretRecoveryResult $result): array
    {
        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository->method('search')->willReturn($this->searchResult([$this->app($name)]));

        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->method('recoverNow')->willReturn($result);

        [$io, $output] = $this->io();
        $exitCode = (new RecoverAppSecretCommand($appRepository, $rotationService))($io, $name);

        return [$exitCode, $output];
    }

    /**
     * Runs the recover path for an app named $name whose recoverNow() throws $failure.
     *
     * @return array{int, BufferedOutput}
     */
    private function runRecoverWithFailure(string $name, AppException $failure): array
    {
        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository->method('search')->willReturn($this->searchResult([$this->app($name)]));

        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->method('recoverNow')->willThrowException($failure);

        [$io, $output] = $this->io();
        $exitCode = (new RecoverAppSecretCommand($appRepository, $rotationService))($io, $name);

        return [$exitCode, $output];
    }

    private function app(string $name): AppEntity
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName($name);

        return $app;
    }

    /**
     * @param list<AppEntity> $apps
     *
     * @return EntitySearchResult<AppCollection>
     */
    private function searchResult(array $apps): EntitySearchResult
    {
        return new EntitySearchResult(
            'app',
            \count($apps),
            new AppCollection($apps),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @return array{SymfonyStyle, BufferedOutput}
     */
    private function io(): array
    {
        $output = new BufferedOutput();

        return [new SymfonyStyle(new ArrayInput([]), $output), $output];
    }
}
