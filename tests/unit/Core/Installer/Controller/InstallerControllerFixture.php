<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Twig\Environment;

/**
 * @internal
 *
 * @phpstan-import-type SupportedLanguages from \Shopware\Core\Installer\Controller\InstallerController
 */
#[Package('checkout')]
final class InstallerControllerFixture
{
    /**
     * @param array<string, object> $services
     */
    public static function getInstallerContainer(Environment $twig, array $services = []): ContainerInterface
    {
        $container = new ContainerBuilder();
        $container->set('twig', $twig);
        $requestStack = new RequestStack();
        $session = new Session(new MockArraySessionStorage());
        $request = new Request([], [], ['_route' => 'installer.start']);
        $request->setSession($session);
        $requestStack->push($request);
        $container->set('request_stack', $requestStack);
        $container->setParameter('shopware.installer.supportedLanguages', self::getSupportedLanguages());
        $container->setParameter('shopware.installer.configurationPreselection', self::getSupportedPreselection());
        $container->setParameter('kernel.shopware_version', Kernel::SHOPWARE_FALLBACK_VERSION);

        foreach ($services as $id => $service) {
            $container->set($id, $service);
        }

        return $container;
    }

    /**
     * @return array{
     *     menu: array{
     *       label: string,
     *       active: bool,
     *       isCompleted: bool
     *     }[],
     *     supportedLanguages: SupportedLanguages,
     *     shopware: array{version: string}
     *   }
     */
    public static function getDefaultViewParams(): array
    {
        return [
            'menu' => [
                [
                    'label' => 'start',
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
                    'label' => 'translation',
                    'active' => false,
                    'isCompleted' => false,
                ],
                [
                    'label' => 'finish',
                    'active' => false,
                    'isCompleted' => false,
                ],
            ],
            'supportedLanguages' => self::getSupportedLanguages(),
            'shopware' => [
                'version' => Kernel::SHOPWARE_FALLBACK_VERSION,
            ],
        ];
    }

    /**
     * @return SupportedLanguages
     */
    public static function getSupportedLanguages(): array
    {
        return [
            'de' => ['id' => 'de-DE', 'label' => 'Deutsch'],
            'en-US' => ['id' => 'en-US', 'label' => 'English (US)'],
            'en' => ['id' => 'en-GB', 'label' => 'English (UK)'],
        ];
    }

    /**
     * @return array<string, array{currency: string}>
     */
    public static function getSupportedPreselection(): array
    {
        return [
            'de' => ['currency' => 'EUR'],
            'en-US' => ['currency' => 'USD'],
            'en' => ['currency' => 'GBP'],
        ];
    }
}
