<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Command\UninstallAppCommand;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(UninstallAppCommand::class)]
class UninstallAppCommandTest extends TestCase
{
    private AbstractAppLifecycle&Stub $appLifecycle;

    /**
     * @var Stub&EntityRepository<AppCollection>
     */
    private EntityRepository&Stub $appRepository;

    private UninstallAppCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appLifecycle = static::createStub(AbstractAppLifecycle::class);
        $this->appRepository = static::createStub(EntityRepository::class);
        $this->command = new UninstallAppCommand($this->appLifecycle, $this->appRepository);
    }

    #[TestDox('--skip-theme-compile sets the skip-theme-compilation state on the context delegated to the lifecycle')]
    public function testSkipThemeCompileSetsState(): void
    {
        $this->stubAppFound();
        $captured = $this->captureLifecycleDelete();

        $tester = new CommandTester($this->command);
        $status = $tester->execute(['name' => 'AcmeApp', '--skip-theme-compile' => true]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertNotNull($captured(), 'AbstractAppLifecycle::delete was not invoked');
        static::assertTrue($captured()['context']->hasState(AbstractAppLifecycle::STATE_SKIP_THEME_COMPILATION));
    }

    #[TestDox('Without --skip-theme-compile, the lifecycle context does not carry the skip-theme-compilation state')]
    public function testWithoutSkipThemeCompileDoesNotSetState(): void
    {
        $this->stubAppFound();
        $captured = $this->captureLifecycleDelete();

        $tester = new CommandTester($this->command);
        $status = $tester->execute(['name' => 'AcmeApp']);

        static::assertSame(Command::SUCCESS, $status);
        static::assertNotNull($captured(), 'AbstractAppLifecycle::delete was not invoked');
        static::assertFalse($captured()['context']->hasState(AbstractAppLifecycle::STATE_SKIP_THEME_COMPILATION));
    }

    #[TestDox('--keep-user-data forwards true as the keepUserData arg to the lifecycle')]
    public function testKeepUserDataOptionIsForwarded(): void
    {
        $this->stubAppFound();
        $captured = $this->captureLifecycleDelete();

        $tester = new CommandTester($this->command);
        $status = $tester->execute(['name' => 'AcmeApp', '--keep-user-data' => true]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertNotNull($captured(), 'AbstractAppLifecycle::delete was not invoked');
        static::assertTrue($captured()['keepUserData']);
    }

    #[TestDox('Without --keep-user-data, the lifecycle receives false as the keepUserData arg')]
    public function testWithoutKeepUserDataDefaultsToFalse(): void
    {
        $this->stubAppFound();
        $captured = $this->captureLifecycleDelete();

        $tester = new CommandTester($this->command);
        $status = $tester->execute(['name' => 'AcmeApp']);

        static::assertSame(Command::SUCCESS, $status);
        static::assertNotNull($captured(), 'AbstractAppLifecycle::delete was not invoked');
        static::assertFalse($captured()['keepUserData']);
    }

    #[TestDox('Throws InvalidArgumentException when the name argument is not a string')]
    public function testThrowsWhenNameArgumentIsNotString(): void
    {
        $input = static::createStub(InputInterface::class);
        $input->method('getArgument')->willReturn(null);

        $execute = new \ReflectionMethod(UninstallAppCommand::class, 'execute');

        $this->expectExceptionObject(new \InvalidArgumentException('Argument $name must be an string'));

        $execute->invoke($this->command, $input, new BufferedOutput());
    }

    #[TestDox('Returns FAILURE with an error when the named app is not installed')]
    public function testFailsWhenAppNotFound(): void
    {
        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new AppCollection([]));
        $this->appRepository->method('search')->willReturn($result);

        $tester = new CommandTester($this->command);
        $status = $tester->execute(['name' => 'Nope']);

        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString('No app with name "Nope" installed.', $tester->getDisplay());
    }

    private function stubAppFound(): void
    {
        $app = new AppEntity();
        $app->setUniqueIdentifier('app-id');
        $app->assign(['id' => 'app-id', 'name' => 'AcmeApp', 'aclRoleId' => 'role-id']);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new AppCollection([$app]));
        $this->appRepository->method('search')->willReturn($result);
    }

    /**
     * Returns a closure that yields the most recent AbstractAppLifecycle::delete call's $context and $keepUserData args.
     *
     * @return \Closure(): ?array{context: Context, keepUserData: bool}
     */
    private function captureLifecycleDelete(): \Closure
    {
        /** @var ?array{context: Context, keepUserData: bool} $captured */
        $captured = null;
        $this->appLifecycle->method('delete')
            ->willReturnCallback(static function (string $name, array $config, Context $context, bool $keepUserData) use (&$captured): void {
                $captured = ['context' => $context, 'keepUserData' => $keepUserData];
            });

        return function () use (&$captured) {
            return $captured;
        };
    }
}
