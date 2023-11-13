<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\Finish\Notifier;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Package('core')]
class SelectLanguagesController extends InstallerController
{
    public function __construct(private readonly Notifier $notifier)
    {
    }

    #[Route(path: '/installer', name: 'installer.language-selection', methods: ['GET'])]
    public function languageSelection(): Response
    {
        $this->notifier->doTrackEvent(Notifier::EVENT_INSTALL_STARTED);

        return $this->renderInstaller('@Installer/installer/language-selection.html.twig');
    }
}
