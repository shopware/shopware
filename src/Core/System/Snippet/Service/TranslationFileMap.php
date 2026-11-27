<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('discovery')]
final class TranslationFileMap
{
    private const PLATFORM_BUNDLES = [
        'Administration' => 'administration.json',
        'Core' => 'messages.json',
        'Storefront' => 'storefront.json',
    ];

    private const PLUGIN_BUNDLES = [
        'Storefront',
        'Administration',
    ];

    /**
     * The keys are paths relative to the root of a locale in the translations repository, which is also the layout of
     * the `translations` folder in the translations archive.
     *
     * @return array<string, string>
     */
    public static function forLocale(TranslationConfig $config, string $locale, string $localePath): array
    {
        $destinationByPath = [];

        foreach (self::PLATFORM_BUNDLES as $bundle => $fileName) {
            $destinationByPath[self::remotePath($locale, 'Platform', $bundle, $fileName)] = Path::join(
                $localePath,
                'Platform',
                // Symfony loads the core bundle by its message domain pattern
                $bundle === 'Core' ? 'messages.' . $locale . '.base.json' : $fileName,
            );
        }

        foreach ($config->plugins as $plugin) {
            foreach (self::PLUGIN_BUNDLES as $bundle) {
                $fileName = strtolower($bundle) . '.json';
                $scope = 'Plugins/' . $plugin;

                $destinationByPath[self::remotePath($locale, $scope, $bundle, $fileName)] = Path::join($localePath, $scope, $fileName);
            }
        }

        return $destinationByPath;
    }

    private static function remotePath(string $locale, string $scope, string $bundle, string $fileName): string
    {
        return \sprintf('%s/%s/%s/%s', $locale, $scope, $bundle, $fileName);
    }
}
