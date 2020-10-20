<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Command\UninstallAppCommand;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\App\StorefrontPluginRegistryTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

class UninstallAppCommandTest extends TestCase
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

    public function testUninstall(): void
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

        $commandTester = new CommandTester($this->getContainer()->get(UninstallAppCommand::class));

        $commandTester->execute(['name' => 'SwagApp']);

        static::assertEquals(0, $commandTester->getStatusCode());

        static::assertStringContainsString('[OK] App uninstalled successfully.', $commandTester->getDisplay());
    }

    public function testUninstallWithNotFoundApp(): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(UninstallAppCommand::class));

        $commandTester->execute(['name' => 'SwagApp']);

        static::assertEquals(1, $commandTester->getStatusCode());

        static::assertStringContainsString('[ERROR] No app with name "SwagApp" installed.', $commandTester->getDisplay());
    }
}
