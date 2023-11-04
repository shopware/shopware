<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * @internal
 */
trait InstallerControllerTestTrait
{
    /**
     * @param array<string, object> $services
     */
    private function getInstallerContainer(Environment $twig, array $services = []): ContainerInterface
    {
        $container = new ContainerBuilder();
        $container->set('twig', $twig);
        $requestStack = new RequestStack();
        $requestStack->push(new Request([], [], ['_route' => 'installer.language-selection']));
        $container->set('request_stack', $requestStack);
        $container->setParameter('shopware.installer.supportedLanguages', ['en' => 'en-GB', 'de' => 'de-DE']);
        $container->setParameter('kernel.shopware_version', Kernel::SHOPWARE_FALLBACK_VERSION);

        foreach ($services as $id => $service) {
            $container->set($id, $service);
        }

        return $container;
    }

    /**
     * @return array{menu: array{label: string, active: bool, isCompleted: bool}[], supportedLanguages: string[], shopware: array{version: string}}
     */
    private function getDefaultViewParams(): array
    {
        return [
            'menu' => [
                [
                    'label' => 'language-selection',
                    'active' => true,
                    'isCompleted' => false,
                ],
                [
                    'label' => 'requirements',
                    'active' => false,
                    'isCompleted' => false,
                ],
                [
                    'label' => 'license',
                    'active' => false,
                    'isCompleted' => false,
                ],
                [
                    'label' => 'database-configuration',
                    'active' => false,
                    'isCompleted' => false,
                ],
                [
                    'label' => 'database-import',
                    'active' => false,
                    'isCompleted' => false,
                ],
                [
                    'label' => 'configuration',
                    'active' => false,
                    'isCompleted' => false,
                ],
                [
                    'label' => 'finish',
                    'active' => false,
                    'isCompleted' => false,
                ],
            ],
            'supportedLanguages' => ['en', 'de'],
            'shopware' => [
                'version' => Kernel::SHOPWARE_FALLBACK_VERSION,
            ],
        ];
    }
}
