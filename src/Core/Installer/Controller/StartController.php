<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
class StartController extends InstallerController
{
    public function __construct()
    {
    }

    #[Route(path: '/installer', name: 'installer.start', methods: ['GET'])]
    public function start(Request $request): Response
    {
        // Check if the wizard was called from the wen installer
        if ($request->query->has('ext_steps')) {
            $this->setInitialState($request);

            return $this->redirectToRoute('installer.requirements');
        }

        return $this->renderInstaller('@Installer/installer/welcome.html.twig');
    }

    private function setInitialState(Request $request): void
    {
        $session = $request->getSession();
        $session->set('extendSteps', true);
    }
}
