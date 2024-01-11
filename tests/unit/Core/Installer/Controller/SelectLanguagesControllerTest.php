<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Controller\InstallerController;
use Shopware\Core\Installer\Controller\SelectLanguagesController;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(SelectLanguagesController::class)]
#[CoversClass(InstallerController::class)]
class SelectLanguagesControllerTest extends TestCase
{
    use InstallerControllerTestTrait;

    public function testLanguageSelectionRoute(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(static::once())->method('render')
            ->with('@Installer/installer/language-selection.html.twig', $this->getDefaultViewParams())
            ->willReturn('languages');

        $controller = new SelectLanguagesController();
        $controller->setContainer($this->getInstallerContainer($twig));

        $response = $controller->languageSelection();
        static::assertSame('languages', $response->getContent());
    }
}
