<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Installer\Finish\Notifier;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
class SelectLanguagesController extends InstallerController
{
    private Notifier $notifier;

    public function __construct(Notifier $notifier)
    {
        $this->notifier = $notifier;
    }

    /**
     * @Since("6.4.15.0")
     * @Route("/installer", name="installer.language-selection", methods={"GET"})
     */
    public function languageSelection(): Response
    {
        $this->notifier->doTrackEvent(Notifier::EVENT_INSTALL_STARTED);

        return $this->renderInstaller('@Installer/installer/language-selection.html.twig');
    }
}
