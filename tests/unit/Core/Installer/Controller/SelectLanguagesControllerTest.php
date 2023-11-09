<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Controller\SelectLanguagesController;
use Shopware\Core\Installer\Finish\Notifier;
use Twig\Environment;

/**
 * @internal
 *
 * @covers \Shopware\Core\Installer\Controller\SelectLanguagesController
 * @covers \Shopware\Core\Installer\Controller\InstallerController
 */
class SelectLanguagesControllerTest extends TestCase
{
    use InstallerControllerTestTrait;

    public function testLanguageSelectionRoute(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(static::once())->method('render')
            ->with('@Installer/installer/language-selection.html.twig', $this->getDefaultViewParams())
            ->willReturn('languages');

        $notifier = $this->createMock(Notifier::class);
        $notifier->expects(static::once())->method('doTrackEvent')
            ->with(Notifier::EVENT_INSTALL_STARTED);

        $controller = new SelectLanguagesController($notifier);
        $controller->setContainer($this->getInstallerContainer($twig));

        $response = $controller->languageSelection();
        static::assertSame('languages', $response->getContent());
    }
}
