<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppService;
use Shopware\Core\Framework\App\Command\AppPrinter;
use Shopware\Core\Framework\App\Command\RefreshAppCommand;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleIterator;
use Shopware\Core\Framework\App\Validation\ManifestValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class RefreshAppCommandTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private EntityRepository $appRepository;

    protected function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
    }

    public function testRefreshWithoutPermissions(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/withoutPermissions'));
        $commandTester->setInputs(['yes']);

        $commandTester->execute([]);

        static::assertSame(0, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        // header
        static::assertMatchesRegularExpression('/.*App\s+Label\s+Version\s+Author\s+\n.*/', $display);
        // content
        static::assertMatchesRegularExpression('/.*withoutPermissions\s+Swag App Test\s+1.0.0\s+shopware AG\s+\n.*/', $display);
    }

    public function testRefreshWithForce(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/withPermissions'));

        $commandTester->execute(['-f' => true]);

        static::assertSame(0, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        // header
        static::assertMatchesRegularExpression('/.*App\s+Label\s+Version\s+Author\s+\n.*/', $display);
        // content
        static::assertMatchesRegularExpression('/.*withPermissions\s+Swag App Test\s+1.0.0\s+shopware AG\s+\n.*/', $display);
    }

    public function testRefreshCancel(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/withoutPermissions'));
        $commandTester->setInputs(['no']);

        $commandTester->execute([]);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('Aborting due to user input.', $commandTester->getDisplay());
    }

    public function testRefreshWithPermissionsOnInstall(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/withPermissions'));
        $commandTester->setInputs(['yes', 'yes', 'yes']);

        $commandTester->execute([]);

        static::assertSame(0, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        static::assertStringContainsString('[CAUTION] App "withPermissions" should be installed', $display);
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

        // header app list
        static::assertMatchesRegularExpression('/.*App\s+Label\s+Version\s+Author\s+\n.*/', $display);
        // content app list
        static::assertMatchesRegularExpression('/.*withPermissions\s+Swag App Test\s+1.0.0\s+shopware AG\s+\n.*/', $display);
    }

    public function testRefreshWithPermissionsOnInstallCancel(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/withPermissions'));
        $commandTester->setInputs(['yes', 'no']);

        $commandTester->execute([]);

        static::assertSame(1, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        static::assertStringContainsString('[CAUTION] App "withPermissions" should be installed', $display);
        // header permissions
        static::assertMatchesRegularExpression('/.*Resource\s+Privileges\s+\n.*/', $display);
        // content permissions
        static::assertMatchesRegularExpression('/.*product\s+write, delete\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*category\s+write\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*order\s+read\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*user_change_me\s+\n.*/', $display);

        static::assertStringContainsString('Aborting due to user input.', $commandTester->getDisplay());
    }

    public function testRefreshWithAllowedHostsOnInstall(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/withAllowedHosts'));
        $commandTester->setInputs(['yes', 'yes', 'yes']);

        $commandTester->execute([]);

        static::assertSame(0, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        static::assertStringContainsString('[CAUTION] App "withAllowedHosts" should be installed', $display);
        // header domains
        static::assertMatchesRegularExpression('/.*Domain\s+\n.*/', $display);
        // content domains
        static::assertMatchesRegularExpression('/.*shopware.com\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*example.com\s+\n.*/', $display);

        // header app list
        static::assertMatchesRegularExpression('/.*App\s+Label\s+Version\s+Author\s+\n.*/', $display);
        // content app list
        static::assertMatchesRegularExpression('/.*withAllowedHosts\s+Swag App Test\s+1.0.0\s+shopware AG\s+\n.*/', $display);
    }

    public function testRefreshWithAllowedHostsOnInstallCancel(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/withAllowedHosts'));
        $commandTester->setInputs(['yes', 'yes', 'no']);

        $commandTester->execute([]);

        static::assertSame(1, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        static::assertStringContainsString('[CAUTION] App "withAllowedHosts" should be installed', $display);
        // header domains
        static::assertMatchesRegularExpression('/.*Domain\s+\n.*/', $display);
        // content domains
        static::assertMatchesRegularExpression('/.*shopware.com\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*example.com\s+\n.*/', $display);

        static::assertStringContainsString('Aborting due to user input.', $commandTester->getDisplay());
    }

    public function testRefreshWithPermissionsOnUpdate(): void
    {
        $this->appRepository->create([[
            'name' => 'withPermissions',
            'path' => __DIR__ . '/_fixtures/withPermissions',
            'version' => '0.9.0',
            'label' => 'test',
            'accessToken' => 'test',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'SwagApp',
            ],
        ]], Context::createDefaultContext());

        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/withPermissions'));
        $commandTester->setInputs(['yes', 'yes', 'yes']);

        $commandTester->execute([]);

        static::assertSame(0, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        static::assertStringContainsString('[CAUTION] App "withPermissions" should be updated', $display);
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

        // header app list
        static::assertMatchesRegularExpression('/.*App\s+Label\s+Version\s+Author\s+\n.*/', $display);
        // content app list
        static::assertMatchesRegularExpression('/.*withPermissions\s+Swag App Test\s+1.0.0\s+shopware AG\s+\n.*/', $display);
    }

    public function testRefreshWithPermissionsOnUpdateCancel(): void
    {
        $this->appRepository->create([[
            'name' => 'withPermissions',
            'path' => __DIR__ . '/_fixtures/withPermissions',
            'version' => '0.9.0',
            'label' => 'test',
            'accessToken' => 'test',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'SwagApp',
            ],
        ]], Context::createDefaultContext());

        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/withPermissions'));
        $commandTester->setInputs(['yes', 'no']);

        $commandTester->execute([]);

        static::assertSame(1, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        static::assertStringContainsString('[CAUTION] App "withPermissions" should be updated', $display);
        // header permissions
        static::assertMatchesRegularExpression('/.*Resource\s+Privileges\s+\n.*/', $display);
        // content permissions
        static::assertMatchesRegularExpression('/.*product\s+write, delete\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*category\s+write\s+\n.*/', $display);
        static::assertMatchesRegularExpression('/.*order\s+read\s+\n.*/', $display);

        static::assertStringContainsString('Aborting due to user input.', $commandTester->getDisplay());
    }

    public function testRefreshWithNothingToDo(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/empty'));

        $commandTester->execute([]);

        static::assertSame(0, $commandTester->getStatusCode());

        static::assertStringContainsString('Nothing to install, update or delete.', $commandTester->getDisplay());
    }

    public function testRefreshRegistrationFailure(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/registrationFailure'));
        $commandTester->setInputs(['yes', 'yes']);

        $commandTester->execute([]);

        static::assertSame(0, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        // header
        static::assertMatchesRegularExpression('/.*App\s+Reason\s+\n.*/', $display);
        // content
        static::assertMatchesRegularExpression('/.*registrationFailure.*The app server provided an invalid proof/', $display);

        $registeredApps = $this->appRepository->search(new Criteria(), Context::createDefaultContext());
        static::assertSame(0, $registeredApps->getTotal());
    }

    public function testRefreshValidationFailure(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->setInputs(['yes', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes']);

        $commandTester->execute([]);

        static::assertSame(1, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        static::assertStringContainsString('[ERROR] The app "validationFailures" is invalid:', $display);
        static::assertStringContainsString('[ERROR] The app "validationFailure" is invalid:', $display);

        $registeredApps = $this->appRepository->search(new Criteria(), Context::createDefaultContext());
        static::assertSame(0, $registeredApps->getTotal());
    }

    public function testRefreshInvalidAppWithNoValidate(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->execute(['-f' => true, '--no-validate' => true]);

        // header app list
        static::assertMatchesRegularExpression('/.*App\s+Label\s+Version\s+Author\s+\n.*/', $commandTester->getDisplay());
        // content app list
        static::assertMatchesRegularExpression('/.*validationFailures\s+Swag App Test\s+1.0.0\s+shopware AG\s+\n.*/', $commandTester->getDisplay());
    }

    public function testRefreshWithLimitation(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->execute(['-f' => true, '--no-validate' => true, 'name' => ['validationFailure']]);

        static::assertStringNotContainsString('withPermissions', $commandTester->getDisplay());
        static::assertStringNotContainsString('withoutPermissions', $commandTester->getDisplay());
    }

    private function createCommand(string $appFolder): RefreshAppCommand
    {
        return new RefreshAppCommand(
            new AppService(
                new AppLifecycleIterator(
                    $this->getContainer()->get('app.repository'),
                    $this->getAppLoader($appFolder)
                ),
                $this->getContainer()->get(AppLifecycle::class)
            ),
            new AppPrinter($this->appRepository),
            $this->getContainer()->get(ManifestValidator::class)
        );
    }
}
