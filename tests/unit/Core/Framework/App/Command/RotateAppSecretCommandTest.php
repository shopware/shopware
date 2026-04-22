<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Command\RotateAppSecretCommand;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[CoversClass(RotateAppSecretCommand::class)]
class RotateAppSecretCommandTest extends TestCase
{
    public function testExecuteRotatesAllActiveApps(): void
    {
        $appId1 = Uuid::randomHex();
        $appId2 = Uuid::randomHex();

        $app1 = new AppEntity();
        $app1->setId($appId1);
        $app1->setName('TestApp1');

        $app2 = new AppEntity();
        $app2->setId($appId2);
        $app2->setName('TestApp2');

        $appRepository = $this->createMock(EntityRepository::class);
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $activeAppsLoader = $this->createMock(ActiveAppsLoader::class);

        $activeAppsLoader->expects($this->once())
            ->method('getActiveApps')
            ->willReturn([
                ['name' => 'TestApp1'],
                ['name' => 'TestApp2'],
            ]);

        $appRepository->expects($this->once())
            ->method('search')
            ->with(
                static::callback(function (Criteria $criteria): bool {
                    $filters = $criteria->getFilters();

                    return \count($filters) === 1
                        && $filters[0] instanceof EqualsAnyFilter
                        && $filters[0]->getField() === 'name'
                        && $filters[0]->getValue() === ['TestApp1', 'TestApp2'];
                }),
                static::isInstanceOf(Context::class)
            )
            ->willReturnCallback(function (Criteria $criteria, Context $context) use ($app1, $app2): EntitySearchResult {
                return new EntitySearchResult(
                    'app',
                    2,
                    new AppCollection([$app1, $app2]),
                    null,
                    $criteria,
                    $context
                );
            });

        $rotationService->expects($this->exactly(2))
            ->method('rotateNow')
            ->willReturnCallback(function (string $appId, Context $context, string $trigger) use ($appId1, $appId2): void {
                static::assertContains($appId, [$appId1, $appId2]);
                static::assertSame(AppSecretRotationService::TRIGGER_CLI, $trigger);
            });

        $command = new RotateAppSecretCommand($appRepository, $rotationService, $activeAppsLoader);
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        static::assertSame(Command::SUCCESS, $exitCode);
        $outputText = $output->fetch();
        static::assertStringContainsString('App TestApp1 secrets rotated successfully.', $outputText);
        static::assertStringContainsString('App TestApp2 secrets rotated successfully.', $outputText);
    }

    public function testExecuteRotatesSingleAppByName(): void
    {
        $appId = Uuid::randomHex();

        $app = new AppEntity();
        $app->setId($appId);
        $app->setName('TestApp');

        $appRepository = $this->createMock(EntityRepository::class);
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $activeAppsLoader = $this->createMock(ActiveAppsLoader::class);

        $activeAppsLoader->expects($this->never())
            ->method('getActiveApps');

        $appRepository->expects($this->once())
            ->method('search')
            ->with(
                static::callback(function (Criteria $criteria): bool {
                    $filters = $criteria->getFilters();

                    return \count($filters) === 1
                        && $filters[0] instanceof EqualsFilter
                        && $filters[0]->getField() === 'name'
                        && $filters[0]->getValue() === 'TestApp';
                }),
                static::isInstanceOf(Context::class)
            )
            ->willReturnCallback(function (Criteria $criteria, Context $context) use ($app): EntitySearchResult {
                return new EntitySearchResult(
                    'app',
                    1,
                    new AppCollection([$app]),
                    null,
                    $criteria,
                    $context
                );
            });

        $rotationService->expects($this->once())
            ->method('rotateNow')
            ->with($appId, static::isInstanceOf(Context::class), AppSecretRotationService::TRIGGER_CLI);

        $command = new RotateAppSecretCommand($appRepository, $rotationService, $activeAppsLoader);
        $input = new ArrayInput(['name' => 'TestApp']);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        static::assertSame(Command::SUCCESS, $exitCode);
        $outputText = $output->fetch();
        static::assertStringContainsString('App TestApp secrets rotated successfully.', $outputText);
    }

    public function testExecuteReturnsSuccessWhenNoActiveApps(): void
    {
        $appRepository = $this->createMock(EntityRepository::class);
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $activeAppsLoader = $this->createMock(ActiveAppsLoader::class);

        $activeAppsLoader->expects($this->once())
            ->method('getActiveApps')
            ->willReturn([]);

        $appRepository->expects($this->never())
            ->method('search');

        $rotationService->expects($this->never())
            ->method('rotateNow');

        $command = new RotateAppSecretCommand($appRepository, $rotationService, $activeAppsLoader);
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        static::assertSame(Command::SUCCESS, $exitCode);
        $outputText = $output->fetch();
        static::assertStringContainsString('No active apps found.', $outputText);
    }

    public function testExecuteReturnsFailureWhenSpecificAppNotFound(): void
    {
        $appRepository = $this->createMock(EntityRepository::class);
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $activeAppsLoader = $this->createMock(ActiveAppsLoader::class);

        $activeAppsLoader->expects($this->never())
            ->method('getActiveApps');

        $appRepository->expects($this->once())
            ->method('search')
            ->with(
                static::callback(function (Criteria $criteria): bool {
                    $filters = $criteria->getFilters();

                    return \count($filters) === 1
                        && $filters[0] instanceof EqualsFilter
                        && $filters[0]->getField() === 'name'
                        && $filters[0]->getValue() === 'NonExistentApp';
                }),
                static::isInstanceOf(Context::class)
            )
            ->willReturnCallback(function (Criteria $criteria, Context $context): EntitySearchResult {
                return new EntitySearchResult(
                    'app',
                    0,
                    new AppCollection([]),
                    null,
                    $criteria,
                    $context
                );
            });

        $rotationService->expects($this->never())
            ->method('rotateNow');

        $command = new RotateAppSecretCommand($appRepository, $rotationService, $activeAppsLoader);
        $input = new ArrayInput(['name' => 'NonExistentApp']);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        static::assertSame(Command::FAILURE, $exitCode);
        $outputText = $output->fetch();
        static::assertStringContainsString('No app found for "NonExistentApp".', $outputText);
    }

    public function testExecuteReturnsFailureWhenRotationFails(): void
    {
        $appId = Uuid::randomHex();

        $app = new AppEntity();
        $app->setId($appId);
        $app->setName('TestApp');

        $appRepository = $this->createMock(EntityRepository::class);
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $activeAppsLoader = $this->createMock(ActiveAppsLoader::class);

        $activeAppsLoader->expects($this->once())
            ->method('getActiveApps')
            ->willReturn([
                ['name' => 'TestApp'],
            ]);

        $appRepository->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria, Context $context) use ($app): EntitySearchResult {
                return new EntitySearchResult(
                    'app',
                    1,
                    new AppCollection([$app]),
                    null,
                    $criteria,
                    $context
                );
            });

        $rotationService->expects($this->once())
            ->method('rotateNow')
            ->willThrowException(new \RuntimeException('Registration failed'));

        $command = new RotateAppSecretCommand($appRepository, $rotationService, $activeAppsLoader);
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        static::assertSame(Command::FAILURE, $exitCode);
        $outputText = $output->fetch();
        static::assertStringContainsString('App TestApp secret rotation failed due: Registration failed', $outputText);
    }
}
