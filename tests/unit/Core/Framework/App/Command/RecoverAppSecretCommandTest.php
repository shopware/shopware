<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Command\RecoverAppSecretCommand;
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
        $rotationService->method('findAppsWithUnconfirmedSecrets')->willReturn(new AppCollection([$this->app('StuckApp')]));

        [$io, $output] = $this->io();
        $exitCode = (new RecoverAppSecretCommand($this->createMock(EntityRepository::class), $rotationService))($io);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('StuckApp', $output->fetch());
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
            ->with($app->getId(), static::isInstanceOf(Context::class));

        [$io, $output] = $this->io();
        $exitCode = (new RecoverAppSecretCommand($appRepository, $rotationService))($io, 'Recoverable');

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('Re-registered app "Recoverable" with a fresh secret.', $output->fetch());
    }

    public function testRecoverReportsSuccessWhenThereIsNothingToRecover(): void
    {
        [$exitCode, $output] = $this->runRecoverWithFailure('Clean', AppException::appSecretRotationNothingToRecover('Clean'));

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('Clean', $output->fetch());
    }

    public function testRecoverFailsWhenThePendingSecretWasAlreadyClaimed(): void
    {
        [$exitCode, $output] = $this->runRecoverWithFailure('Taken', AppException::appSecretRotationClaimed('Taken'));

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('Taken', $output->fetch());
    }

    public function testRecoverReportsUnknownOutcomeWhenTheAppDidNotConfirm(): void
    {
        [$exitCode, $output] = $this->runRecoverWithFailure('Flaky', AppException::registrationFailed('Flaky', 'connection timed out'));

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('unknown', $output->fetch());
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
