<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

abstract class InstallerController extends AbstractController
{
    private Environment $twig;

    private const ROUTES = [
        'installer.language-selection' => 'language-selection',
        'installer.requirements' => 'requirements',
        'installer.license' => 'license',
        'installer.database-configuration' => 'database-configuration',
        'installer.database-import' => 'database-import',
        'installer.configuration' => 'configuration',
        'installer.finish' => 'finish',
    ];

    public function setTwig(Environment $twig): void
    {
        $this->twig = $twig;
    }

    protected function renderInstaller(string $view, array $parameters = []): Response
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();

        if ($request === null) {
            $request = new Request();
        }

        $parameters['menu'] = $this->getMenuData($request);
        $parameters['supportedLanguages'] = $this->container->getParameter('shopware.installer.supportedLanguages');
        $parameters['shopware']['version'] = $this->container->getParameter('kernel.shopware_version');

        return $this->render($view, $parameters);
    }

    private function getMenuData(Request $request): array
    {
        $currentFound = false;
        $menu = [];
        foreach (self::ROUTES as $route => $name) {
            if ($route === $request->attributes->get('_route')) {
                $currentFound = true;
            }

            $menu[] = [
                'label' => $name,
                'active' => $route === $request->attributes->get('_route'),
                'isCompleted' => !$currentFound,
            ];
        }

        return $menu;
    }
}
