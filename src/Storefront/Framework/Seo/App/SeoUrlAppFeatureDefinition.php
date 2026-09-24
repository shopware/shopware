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
use Shopware\Core\Framework\Util\Json;

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
        $claimedByOtherApps = $this->pathsOfOtherApps($appName);
        $declaredBy = [];

        foreach ($configs as $config) {
            foreach (array_unique($config->getPaths()) as $path) {
                if (ValidSeoPathInfo::sanitize($path) !== $path) {
                    throw SeoException::appSeoUrlPathInvalid($config->getName(), $path);
                }

                $normalizedPath = self::normalize($path);

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

    /**
     * @return array<string, string> normalized path => app name
     */
    private function pathsOfOtherApps(string $appName): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT `app_name`, `payload` FROM `app_feature` WHERE `type` = :type AND `app_name` != :appName',
            ['type' => self::TYPE, 'appName' => $appName],
        );

        $claimedBy = [];

        foreach ($rows as $row) {
            $paths = Json::decodeToArray((string) $row['payload'])['paths'] ?? [];

            if (!\is_array($paths)) {
                continue;
            }

            foreach ($paths as $path) {
                if (\is_string($path)) {
                    $claimedBy[self::normalize($path)] = (string) $row['app_name'];
                }
            }
        }

        return $claimedBy;
    }

    private function isInUse(string $normalizedPath, string $ownRouteNamePrefix): bool
    {
        if ($this->routeBlocklistService->isPathBlocked($normalizedPath)) {
            return true;
        }

        return $this->connection->fetchOne(
            'SELECT 1
             FROM `sales_channel_domain`
             INNER JOIN `seo_url`
                ON `seo_url`.`sales_channel_id` = `sales_channel_domain`.`sales_channel_id`
                AND `seo_url`.`language_id` = `sales_channel_domain`.`language_id`
             WHERE `seo_url`.`seo_path_info` = :path
               AND `seo_url`.`is_canonical` = 1
               AND `seo_url`.`is_deleted` = 0
               AND `seo_url`.`route_name` NOT LIKE :ownRoutes
             LIMIT 1',
            [
                'path' => $normalizedPath,
                'ownRoutes' => addcslashes($ownRouteNamePrefix, '\\%_') . '%',
            ],
        ) !== false;
    }

    private static function normalize(string $path): string
    {
        return mb_strtolower(ltrim($path, '/'));
    }
}
