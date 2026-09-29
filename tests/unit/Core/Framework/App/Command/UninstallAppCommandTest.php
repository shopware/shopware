<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Command\UninstallAppCommand;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UninstallAppCommand::class)]
class UninstallAppCommandTest extends TestCase
{
    private AbstractAppLifecycle&Stub $appLifecycle;

    private AppStorage&Stub $appStorage;

    private UninstallAppCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appLifecycle = static::createStub(AbstractAppLifecycle::class);
        $this->appStorage = static::createStub(AppStorage::class);
        $this->command = new UninstallAppCommand($this->appLifecycle, $this->appStorage);
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

    #[TestDox('Returns FAILURE with an error when the named app is not installed')]
    public function testFailsWhenAppNotFound(): void
    {
        $this->appStorage->method('findByName')->willReturn(null);

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

        $this->appStorage->method('findByName')->willReturn($app);
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
        $this->appLifecycle->method('uninstall')
            ->willReturnCallback(static function (string $name, array $config, Context $context, bool $keepUserData) use (&$captured): void {
                $captured = ['context' => $context, 'keepUserData' => $keepUserData];
            });

        return function () use (&$captured) {
            return $captured;
        };
    }
}
