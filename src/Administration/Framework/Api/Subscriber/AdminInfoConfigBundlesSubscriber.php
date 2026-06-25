<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\Api\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Administration\Framework\App\ActiveAdminAppLoader;
use Shopware\Administration\Framework\Twig\ViteFileAccessorDecorator;
use Shopware\Core\Framework\Api\Event\AdminInfoConfigEvent;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Util\AssetValidation\AdministrationExtensionAssetValidator;
use Shopware\Core\Framework\Plugin\Util\AssetValidation\AdministrationExtensionAssetViolation;
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
        private AdministrationExtensionAssetValidator $administrationExtensionAssetValidator,
        private LoggerInterface $logger,
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

            try {
                $viteEntryPoints = $this->viteFileAccessorDecorator->getBundleData($bundle);
            } catch (\Throwable $exception) {
                $this->logger->warning('Skipping Administration extension asset metadata because it could not be read.', [
                    'bundleName' => $bundle->getName(),
                    'technicalBundleName' => $this->administrationExtensionAssetValidator->getTechnicalBundleName($bundle),
                    'entrypointsFilePath' => $this->administrationExtensionAssetValidator->getEntrypointsFilePath($bundle),
                    'exception' => $exception,
                ]);

                continue;
            }

            $technicalBundleName = $this->administrationExtensionAssetValidator->getTechnicalBundleName($bundle);
            $styles = $this->filterAssetUrls($bundle, $viteEntryPoints['entryPoints'][$technicalBundleName]['css'] ?? [], 'css');
            $scripts = $this->filterAssetUrls($bundle, $viteEntryPoints['entryPoints'][$technicalBundleName]['js'] ?? [], 'js');
            $this->logValidationViolations(
                $this->administrationExtensionAssetValidator->validateEntrypointsData($bundle, $viteEntryPoints),
            );
            $baseUrl = $this->getBaseUrl($bundle);

            if (($viteEntryPoints['entryPoints'][$technicalBundleName]['js'] ?? []) !== [] && $scripts === [] && $baseUrl === null) {
                $this->logger->warning('Skipping Administration extension asset bundle because no JavaScript asset remains after validation.', [
                    'bundleName' => $bundle->getName(),
                    'technicalBundleName' => $technicalBundleName,
                    'entrypointsFilePath' => $this->administrationExtensionAssetValidator->getEntrypointsFilePath($bundle),
                ]);

                continue;
            }

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

    /**
     * @return list<string>
     */
    private function filterAssetUrls(Bundle $bundle, mixed $assetUrls, string $assetType): array
    {
        return $this->administrationExtensionAssetValidator->filterAssetUrls($bundle, $assetUrls, $assetType)->assets;
    }

    /**
     * @param list<AdministrationExtensionAssetViolation> $violations
     */
    private function logValidationViolations(array $violations): void
    {
        foreach ($violations as $violation) {
            $message = $violation->isMissingAsset()
                ? 'Skipping missing Administration extension asset.'
                : 'Skipping invalid Administration extension asset.';

            $this->logger->warning($message, $violation->toLogContext());
        }
    }
}
