<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Controller\DatabaseConfigurationController;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Twig\Environment;

/**
 * @internal
 * @covers \Shopware\Core\Installer\Controller\DatabaseConfigurationController
 * @covers \Shopware\Core\Installer\Controller\InstallerController
 */
class DatabaseConfigurationControllerTest extends TestCase
{
    use InstallerControllerTestTrait;

    public function testDatabaseConfigurationRoute(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(static::once())->method('render')
            ->with(
                '@Installer/installer/database-configuration.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'connectionInfo' => new DatabaseConnectionInformation(),
                    'error' => null,
                ])
            )
            ->willReturn('config');

        $controller = new DatabaseConfigurationController();
        $controller->setContainer($this->getInstallerContainer($twig));

        $response = $controller->databaseConfiguration();
        static::assertSame('config', $response->getContent());
    }
}
