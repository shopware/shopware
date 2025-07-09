<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @property ContainerInterface $container
 *
 * @internal
 *
 * @phpstan-type SupportedLanguages array<string, array{id: string, label: string}>
 */
#[Package('framework')]
abstract class InstallerController extends AbstractController
{
    private const ROUTES = [
        'installer.welcome' => 'welcome',
        'installer.requirements' => 'requirements',
        'installer.license' => 'license',
        'installer.database-configuration' => 'database-configuration',
        'installer.database-import' => 'database-import',
        'installer.configuration' => 'configuration',
        'installer.finish' => 'finish',
    ];

    /**
     * @param array<string, mixed> $parameters
     */
    protected function renderInstaller(string $view, array $parameters = []): Response
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();

        if ($request !== null) {
            $parameters['menu'] = $this->getMenuData($request);
        }

        /** @var ContainerInterface $container */
        $container = $this->container;

        if (!\array_key_exists('supportedLanguages', $parameters)) {
            /** @var SupportedLanguages $supportedLanguages */
            $supportedLanguages = $container->getParameter('shopware.installer.supportedLanguages');
            ksort($supportedLanguages);
            $parameters['supportedLanguages'] = $supportedLanguages;
        }
        $parameters['shopware']['version'] = $container->getParameter('kernel.shopware_version');

        return $this->render($view, $parameters);
    }

    /**
     * @return array{label: string, active: bool, isCompleted: bool}[]
     */
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
