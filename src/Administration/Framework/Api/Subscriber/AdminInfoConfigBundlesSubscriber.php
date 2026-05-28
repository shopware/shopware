<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\Api\Subscriber;

use Shopware\Administration\Framework\App\ActiveAdminAppLoader;
use Shopware\Administration\Framework\Twig\ViteFileAccessorDecorator;
use Shopware\Core\Framework\Api\Event\AdminInfoConfigEvent;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Kernel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class AdminInfoConfigBundlesSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Kernel $kernel,
        private RouterInterface $router,
        private ActiveAdminAppLoader $activeAdminAppLoader,
        private Filesystem $filesystem,
        private ViteFileAccessorDecorator $viteFileAccessorDecorator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AdminInfoConfigEvent::class => 'enrichBundles',
        ];
    }

    public function enrichBundles(AdminInfoConfigEvent $event): void
    {
        $event->addConfig('bundles', $this->buildBundles());
    }

    /**
     * @return array<string, array{
     *     type: 'plugin',
     *     css: list<string>,
     *     js: list<string>,
     *     baseUrl: ?string
     * }|array{
     *     type: 'app',
     *     name: string,
     *     active: bool,
     *     integrationId: string,
     *     baseUrl: string,
     *     version: string,
     *     permissions: array<string, list<string>>
     * }>
     */
    private function buildBundles(): array
    {
        $assets = [];

        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }

            $viteEntryPoints = $this->viteFileAccessorDecorator->getBundleData($bundle);

            $technicalBundleName = $this->getTechnicalBundleName($bundle);
            $styles = $viteEntryPoints['entryPoints'][$technicalBundleName]['css'] ?? [];
            $scripts = $viteEntryPoints['entryPoints'][$technicalBundleName]['js'] ?? [];
            $baseUrl = $this->getBaseUrl($bundle);

            if ($styles === [] && $scripts === [] && $baseUrl === null) {
                continue;
            }

            $assets[$bundle->getName()] = [
                'css' => $styles,
                'js' => $scripts,
                'baseUrl' => $baseUrl,
                'type' => 'plugin',
            ];
        }

        foreach ($this->activeAdminAppLoader->getActiveAdminApps() as $app) {
            $assets[$app['name']] = [
                'active' => (bool) $app['active'],
                'integrationId' => $app['integrationId'],
                'type' => 'app',
                'baseUrl' => $app['baseUrl'],
                'permissions' => $app['privileges'],
                'version' => $app['version'],
                'name' => $app['name'],
            ];
        }

        return $assets;
    }

    private function getBaseUrl(Bundle $bundle): ?string
    {
        if ($bundle->getAdminBaseUrl()) {
            return $bundle->getAdminBaseUrl();
        }

        if (!$this->filesystem->exists($bundle->getPath() . '/Resources/public/meteor-app/index.html')) {
            return null;
        }

        try {
            return $this->router->generate(
                'administration.plugin.index',
                [
                    /**
                     * Adopted from symfony, as they also strip the bundle suffix:
                     * https://github.com/symfony/symfony/blob/7.2/src/Symfony/Bundle/FrameworkBundle/Command/AssetsInstallCommand.php#L128
                     *
                     * @see Plugin\Util\AssetService::getTargetDirectory
                     */
                    'pluginName' => preg_replace('/bundle$/', '', mb_strtolower($bundle->getName())),
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function getTechnicalBundleName(Bundle $bundle): string
    {
        return str_replace('_', '-', $bundle->getContainerPrefix());
    }
}
