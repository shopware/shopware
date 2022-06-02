<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Controller;

use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 *
 * @Route(defaults={"_routeScope"={"installer"}})
 */
class SelectLanguagesController extends InstallerController
{
    /**
     * @Since("6.4.13.0")
     * @Route("/installer", name="installer.language-selection", methods={"GET"})
     */
    public function languageSelection(): Response
    {
        return $this->renderInstaller('@Maintenance/installer/language-selection.html.twig');
    }
}
