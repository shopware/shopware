<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
class WelcomeController extends InstallerController
{
    public function __construct()
    {
    }

    #[Route(path: '/installer', name: 'installer.welcome', methods: ['GET'])]
    public function welcome(): Response
    {
        return $this->renderInstaller('@Installer/installer/welcome.html.twig');
    }
}
