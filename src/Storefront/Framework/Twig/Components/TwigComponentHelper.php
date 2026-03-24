<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Components;

use Doctrine\DBAL\Connection;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('framework')]
class TwigComponentHelper
{
    public const COMPONENT_DIRECTORY = 'Resources/views/components/';

    /**
     * @param list<array{name: string, namespace: string, path: string}> $bundleComponents Pre-computed by TwigComponentBundlePass at container build time
     *
     * @internal
     */
    public function __construct(
        private readonly array $bundleComponents,
        private readonly string $projectDir,
        private readonly Connection $connection,
        private readonly SourceResolver $sourceResolver,
        private readonly FilesystemOperator $localFilesystem,
    ) {
    }

    public function getComponents(): TwigComponentCollection
    {
        $components = new TwigComponentCollection();

        foreach ($this->bundleComponents as $data) {
            $components->add(new TwigComponent($data['name'], $data['path'], $data['namespace']));
        }

        foreach ($this->findAppComponentsByTemplate() as $component) {
            $components->add($component);
        }

        return $components;
    }

    public static function getComponentNameFromPath(string $templateRelativePath): string
    {
        if (str_starts_with($templateRelativePath, 'components/')) {
            $templateRelativePath = str_replace('components/', '', $templateRelativePath);
        }

        $componentName = str_replace(\DIRECTORY_SEPARATOR, ':', $templateRelativePath);
        $componentName = substr($componentName, 0, -10); // remove file extension ".html.twig"

        return $componentName;
    }

    /**
     * @return array<string, TwigComponent>
     */
    private function findAppComponentsByTemplate(): array
    {
        $components = [];

        foreach ($this->getAppDirs() as $normalizedDir => $namespace) {
            try {
                $items = $this->localFilesystem->listContents($normalizedDir, true);
            } catch (\Throwable) {
                continue;
            }

            foreach ($items as $item) {
                if (!$item instanceof FileAttributes) {
                    continue;
                }

                $filePath = $item->path();

                if (!str_ends_with($filePath, '.html.twig')) {
                    continue;
                }

                $prefix = rtrim($normalizedDir, '/') . '/';
                $relativePath = substr($filePath, \strlen($prefix));

                // Skip files inside underscore-prefixed directories
                if (str_contains('/' . $relativePath, '/_')) {
                    continue;
                }

                $componentName = self::getComponentNameFromPath($relativePath);

                $absolutePath = Path::canonicalize(Path::join($this->projectDir, $filePath));
                $component = new TwigComponent($componentName, $absolutePath, $namespace);

                $components[$component->getTag()] = $component;
            }
        }

        return $components;
    }

    /**
     * @return array<string, string>
     */
    private function getAppDirs(): array
    {
        $dirs = [];

        $apps = $this->connection->fetchAllAssociative('
            SELECT DISTINCT
                `app`.`name` AS `namespace`
            FROM `app_template`
            INNER JOIN `app` ON `app_template`.`app_id` = `app`.`id`
            WHERE `app_template`.`active` = 1 AND `app`.`active` = 1
            AND `app_template`.`path` LIKE "%components/%"
        ');

        foreach ($apps as $app) {
            try {
                $filesystem = $this->sourceResolver->filesystemForAppName($app['namespace']);
            } catch (\Throwable) {
                continue;
            }

            if (!$filesystem->has(self::COMPONENT_DIRECTORY)) {
                continue;
            }

            $relativeDir = $this->toProjectRelativePath($filesystem->path(self::COMPONENT_DIRECTORY));
            if ($relativeDir === null || !$this->localFilesystem->directoryExists($relativeDir)) {
                continue;
            }

            $dirs[$relativeDir] = $app['namespace'];
        }

        return $dirs;
    }

    /**
     * @return non-empty-string|null Path relative to project dir, using forward slashes (Flysystem)
     */
    private function toProjectRelativePath(string $absolutePath): ?string
    {
        $projectRoot = Path::canonicalize($this->projectDir);
        $target = Path::canonicalize($absolutePath);

        $relative = Path::makeRelative($target, $projectRoot);

        if ($relative === '' || $relative === '.' || str_starts_with($relative, '..')) {
            return null;
        }

        return str_replace('\\', '/', $relative);
    }
}
