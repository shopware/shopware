<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Controller\InstallerController;
use Shopware\Core\Installer\Controller\WelcomeController;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(WelcomeController::class)]
#[CoversClass(InstallerController::class)]
class WelcomeControllerTest extends TestCase
{
    use InstallerControllerTestTrait;

    public function testLanguageSelectionRoute(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with('@Installer/installer/welcome.html.twig', $this->getDefaultViewParams())
            ->willReturn('languages');

        $controller = new WelcomeController();
        $controller->setContainer($this->getInstallerContainer($twig));

        $response = $controller->welcome();
        static::assertSame('languages', $response->getContent());
    }
}
