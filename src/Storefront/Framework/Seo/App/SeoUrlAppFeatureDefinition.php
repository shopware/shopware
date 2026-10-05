<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Content\Seo\Validation\Constraint\ValidSeoPathInfo;
use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinition;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\SeoUrl;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Validation\RouteBlocklistService;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * Maps the manifest `<storefront><seo-url>` elements to `app_feature` rows of type `storefront_seo_url`.
 *
 * @internal
 *
 * @extends AppFeatureDefinition<AppSeoUrlConfig>
 *
 * @phpstan-type SeoUrlPayload array{name: string, routeName: string, hook: string, paths: array<string, string>}
 */
#[Package('inventory')]
final class SeoUrlAppFeatureDefinition extends AppFeatureDefinition
{
    final public const TYPE = 'storefront_seo_url';

    public function __construct(
        private readonly Connection $connection,
        private readonly RouteBlocklistService $routeBlocklistService,
        private readonly AppSeoUrlClaims $claims,
    ) {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getConfigClass(): string
    {
        return AppSeoUrlConfig::class;
    }

    public function fromApp(Manifest $manifest, Filesystem $appFilesystem, string $defaultLocale): array
    {
        $appName = $manifest->getMetadata()->getName();

        return array_map(
            static function (SeoUrl $seoUrl) use ($appName, $defaultLocale): AppSeoUrlConfig {
                $data = $seoUrl->toArray($defaultLocale);

                return new AppSeoUrlConfig(
                    $data['name'],
                    AppSeoUrlRoute::buildRouteName($appName, $data['name']),
                    $data['hook'],
                    $data['path'],
                );
            },
            $manifest->getStorefront()?->getSeoUrls() ?? []
        );
    }

    /**
     * @param list<AppSeoUrlConfig> $configs
     */
    public function validate(array $configs, AppPersistContext $context): void
    {
        if ($configs === []) {
            return;
        }

        $appName = $context->app->getName();
        $ownRouteNamePrefix = AppSeoUrlRoute::routeNamePrefix($appName);
        $claimedByOtherApps = $this->claims->pathsOfOtherApps($appName);
        $hooksOfOtherApps = $this->claims->hooksOfOtherApps($appName);
        $declaredBy = [];
        $declaredHooks = [];

        foreach ($configs as $config) {
            foreach (array_unique($config->getPaths()) as $path) {
                if (ValidSeoPathInfo::sanitize($path) !== $path) {
                    throw SeoException::appSeoUrlPathInvalid($config->getName(), $path);
                }

                $normalizedPath = AppSeoUrlClaims::normalizePath($path);

                if (isset($claimedByOtherApps[$normalizedPath])) {
                    throw SeoException::appSeoUrlPathAlreadyRegistered($config->getName(), $path, $claimedByOtherApps[$normalizedPath]);
                }

                if (($declaredBy[$normalizedPath] ?? $config->getName()) !== $config->getName()) {
                    throw SeoException::appSeoUrlPathAlreadyRegistered($config->getName(), $path, $appName);
                }

                if (!isset($declaredBy[$normalizedPath]) && $this->isInUse($normalizedPath, $ownRouteNamePrefix)) {
                    throw SeoException::appSeoUrlPathInUse($config->getName(), $path);
                }

                $declaredBy[$normalizedPath] = $config->getName();
            }

            $hook = $config->getHook();

            if (isset($hooksOfOtherApps[$hook])) {
                throw SeoException::appSeoUrlHookAlreadyRegistered($config->getName(), $hook, $hooksOfOtherApps[$hook]);
            }

            if (isset($declaredHooks[$hook])) {
                throw SeoException::appSeoUrlHookAlreadyRegistered($config->getName(), $hook, $appName);
            }

            $declaredHooks[$hook] = true;
        }
    }

    /**
     * @return SeoUrlPayload
     */
    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array
    {
        return [
            'name' => $declared->getName(),
            'routeName' => $declared->getRouteName(),
            'hook' => $declared->getHook(),
            'paths' => $declared->getPaths(),
        ];
    }

    /**
     * @param SeoUrlPayload $payload
     */
    public function fromPayload(array $payload): AppSeoUrlConfig
    {
        return new AppSeoUrlConfig(
            $payload['name'],
            $payload['routeName'],
            $payload['hook'],
            $payload['paths'],
        );
    }

    private function isInUse(string $normalizedPath, string $ownRouteNamePrefix): bool
    {
        if ($this->routeBlocklistService->isPathBlocked($normalizedPath)) {
            return true;
        }

        return $this->connection->fetchOne(
            'SELECT 1
             FROM `seo_url`
             WHERE `seo_path_info` = :path
               AND `is_canonical` = 1
               AND `is_deleted` = 0
               AND `route_name` NOT LIKE :ownRoutes
             LIMIT 1',
            [
                'path' => $normalizedPath,
                'ownRoutes' => addcslashes($ownRouteNamePrefix, '\\%_') . '%',
            ],
        ) !== false;
    }
}
