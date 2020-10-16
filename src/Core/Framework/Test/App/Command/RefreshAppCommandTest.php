<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppService;
use Shopware\Core\Framework\App\Command\AppPrinter;
use Shopware\Core\Framework\App\Command\RefreshAppCommand;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleIterator;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\App\StorefrontPluginRegistryTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

class RefreshAppCommandTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPluginRegistryTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    public function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
    }

    public function testRefreshWithoutPermissions(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/withoutPermissions'));
        $commandTester->setInputs(['yes']);

        $commandTester->execute([]);

        static::assertEquals(0, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        // header
        static::assertRegExp('/.*Plugin\s+Label\s+Version\s+Author\s+\n.*/', $display);
        // content
        static::assertRegExp('/.*SwagApp\s+Swag App Test\s+1.0.0\s+shopware AG\s+\n.*/', $display);
    }

    public function testRefreshWithForce(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/withPermissions'));

        $commandTester->execute(['-f' => true]);

        static::assertEquals(0, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        // header
        static::assertRegExp('/.*Plugin\s+Label\s+Version\s+Author\s+\n.*/', $display);
        // content
        static::assertRegExp('/.*SwagApp\s+Swag App Test\s+1.0.0\s+shopware AG\s+\n.*/', $display);
    }

    public function testRefreshCancel(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/withoutPermissions'));
        $commandTester->setInputs(['no']);

        $commandTester->execute([]);

        static::assertEquals(1, $commandTester->getStatusCode());
        static::assertStringContainsString('Aborting due to user input.', $commandTester->getDisplay());
    }

    public function testRefreshWithPermissionsOnInstall(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/withPermissions'));
        $commandTester->setInputs(['yes', 'yes']);

        $commandTester->execute([]);

        static::assertEquals(0, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        static::assertStringContainsString('[CAUTION] App "SwagApp" should be installed', $display);
        // header permissions
        static::assertRegExp('/.*Resource\s+Privileges\s+\n.*/', $display);
        // content permissions
        static::assertRegExp('/.*product\s+write, delete\s+\n.*/', $display);
        static::assertRegExp('/.*category\s+write\s+\n.*/', $display);
        static::assertRegExp('/.*order\s+read\s+\n.*/', $display);

        // header app list
        static::assertRegExp('/.*Plugin\s+Label\s+Version\s+Author\s+\n.*/', $display);
        // content app list
        static::assertRegExp('/.*SwagApp\s+Swag App Test\s+1.0.0\s+shopware AG\s+\n.*/', $display);
    }

    public function testRefreshWithPermissionsOnInstallCancel(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/withPermissions'));
        $commandTester->setInputs(['yes', 'no']);

        $commandTester->execute([]);

        static::assertEquals(1, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        static::assertStringContainsString('[CAUTION] App "SwagApp" should be installed', $display);
        // header permissions
        static::assertRegExp('/.*Resource\s+Privileges\s+\n.*/', $display);
        // content permissions
        static::assertRegExp('/.*product\s+write, delete\s+\n.*/', $display);
        static::assertRegExp('/.*category\s+write\s+\n.*/', $display);
        static::assertRegExp('/.*order\s+read\s+\n.*/', $display);

        static::assertStringContainsString('Aborting due to user input.', $commandTester->getDisplay());
    }

    public function testRefreshWithPermissionsOnUpdate(): void
    {
        $this->appRepository->create([[
            'name' => 'SwagApp',
            'path' => __DIR__ . '/_fixtures/withPermissions',
            'version' => '0.9.0',
            'label' => 'test',
            'accessToken' => 'test',
            'integration' => [
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'SwagApp',
            ],
        ]], Context::createDefaultContext());

        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/withPermissions'));
        $commandTester->setInputs(['yes', 'yes']);

        $commandTester->execute([]);

        static::assertEquals(0, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        static::assertStringContainsString('[CAUTION] App "SwagApp" should be updated', $display);
        // header permissions
        static::assertRegExp('/.*Resource\s+Privileges\s+\n.*/', $display);
        // content permissions
        static::assertRegExp('/.*product\s+write, delete\s+\n.*/', $display);
        static::assertRegExp('/.*category\s+write\s+\n.*/', $display);
        static::assertRegExp('/.*order\s+read\s+\n.*/', $display);

        // header app list
        static::assertRegExp('/.*Plugin\s+Label\s+Version\s+Author\s+\n.*/', $display);
        // content app list
        static::assertRegExp('/.*SwagApp\s+Swag App Test\s+1.0.0\s+shopware AG\s+\n.*/', $display);
    }

    public function testRefreshWithPermissionsOnUpdateCancel(): void
    {
        $this->appRepository->create([[
            'name' => 'SwagApp',
            'path' => __DIR__ . '/_fixtures/withPermissions',
            'version' => '0.9.0',
            'label' => 'test',
            'accessToken' => 'test',
            'integration' => [
                'label' => 'test',
                'writeAccess' => false,
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

        static::assertEquals(1, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        static::assertStringContainsString('[CAUTION] App "SwagApp" should be updated', $display);
        // header permissions
        static::assertRegExp('/.*Resource\s+Privileges\s+\n.*/', $display);
        // content permissions
        static::assertRegExp('/.*product\s+write, delete\s+\n.*/', $display);
        static::assertRegExp('/.*category\s+write\s+\n.*/', $display);
        static::assertRegExp('/.*order\s+read\s+\n.*/', $display);

        static::assertStringContainsString('Aborting due to user input.', $commandTester->getDisplay());
    }

    public function testRefreshWithNothingToDo(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/empty'));

        $commandTester->execute([]);

        static::assertEquals(0, $commandTester->getStatusCode());

        static::assertStringContainsString('Nothing to install, update or delete.', $commandTester->getDisplay());
    }

    public function testRefreshRegistrationFailure(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures/registrationFailure'));
        $commandTester->setInputs(['yes']);

        $commandTester->execute([]);

        static::assertEquals(0, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();

        // header
        static::assertRegExp('/.*Failed\s+\n.*/', $display);
        // content
        static::assertRegExp('/.*SwagApp\s+\n.*/', $display);

        $registeredApps = $this->appRepository->search(new Criteria(), Context::createDefaultContext());
        static::assertEquals(0, $registeredApps->getTotal());
    }

    private function createCommand(string $appFolder): RefreshAppCommand
    {
        return new RefreshAppCommand(
            new AppService(
                new AppLifecycleIterator(
                    $this->getContainer()->get('app.repository'),
                    new AppLoader($appFolder)
                ),
                $this->getContainer()->get(AppLifecycle::class)
            ),
            new AppPrinter($this->appRepository)
        );
    }
}
