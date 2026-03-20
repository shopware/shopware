<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Components;

use Doctrine\DBAL\Connection;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
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
     * @param array<string, array{path: string}> $bundlesMetadata
     *
     * @internal
     */
    public function __construct(
        private array $bundlesMetadata,
        private readonly string $projectDir,
        private readonly NamespaceHierarchyBuilder $namespaceHierarchyBuilder,
        private readonly ComponentMetadataProviderInterface $componentMetadataProvider,
        private readonly Connection $connection,
        private readonly SourceResolver $sourceResolver,
        private readonly FilesystemOperator $localFilesystem,
    ) {
    }

    public function getComponents(bool $includeMetadata = false): TwigComponentCollection
    {
        $components = new TwigComponentCollection();

        foreach ($this->findComponentsByTemplate() as $component) {
            if ($includeMetadata) {
                $componentMetadata = $this->componentMetadataProvider->metadataFor($component->name);
                $component->metadata = $componentMetadata;
            }

            $components->add($component);
        }

        return $components;
    }

    /**
     * @return array<string, TwigComponent>
     */
    private function findComponentsByTemplate(): array
    {
        $dirs = array_merge(
            $this->getBundleDirs(),
            $this->getAppDirs()
        );

        $components = [];

        foreach ($dirs as $normalizedDir => $namespace) {
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

                // Equivalent of Finder::notPath('/_') – skip files inside underscore-prefixed directories
                if (str_contains('/' . $relativePath, '/_')) {
                    continue;
                }

                $componentName = $this->getComponentNameFromPath($relativePath);

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
    private function getBundleDirs(): array
    {
        $namespaceHierarchy = $this->namespaceHierarchyBuilder->buildHierarchy();
        $namespaces = array_keys($namespaceHierarchy);

        $dirs = [];

        foreach ($namespaces as $namespace) {
            if (!isset($this->bundlesMetadata[$namespace])) {
                continue;
            }

            $path = $this->bundlesMetadata[$namespace]['path'];
            $componentDir = Path::join($path, self::COMPONENT_DIRECTORY);

            $relativeDir = $this->toProjectRelativePath($componentDir);
            if ($relativeDir === null || !$this->localFilesystem->directoryExists($relativeDir)) {
                continue;
            }

            $dirs[$relativeDir] = $namespace;
        }

        return $dirs;
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

    private function getComponentNameFromPath(string $templateRelativePath): string
    {
        if (str_starts_with($templateRelativePath, 'components/')) {
            $templateRelativePath = str_replace('components/', '', $templateRelativePath);
        }

        $componentName = str_replace(\DIRECTORY_SEPARATOR, ':', $templateRelativePath);
        $componentName = substr($componentName, 0, -10); // remove file extension ".html.twig"

        return $componentName;
    }
}
