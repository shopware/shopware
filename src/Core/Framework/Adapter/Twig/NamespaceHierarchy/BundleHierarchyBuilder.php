<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpKernel\KernelInterface;

#[Package('framework')]
class BundleHierarchyBuilder implements TemplateNamespaceHierarchyBuilderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Connection $connection
    ) {
    }

    public function buildNamespaceHierarchy(array $namespaceHierarchy): array
    {
        /*
         * Priority system: Lower integer = higher precedence
         * Example: -2 overrides 0, which overrides 1
         * Used only for sorting, then discarded
         */
        $bundles = [];

        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }

            $bundlePath = $bundle->getPath();

            $directory = $bundlePath . '/Resources/views';

            if (!\is_dir($directory)) {
                continue;
            }

            $bundles[$bundle->getName()] = $bundle->getTemplatePriority();
        }

        // Shopware registers bundles in reverse order
        $bundles = array_reverse($bundles);

        $apps = $this->getAppTemplateNamespaces();

        // Extract template_load_priority from app data structure
        /** @var array<int, array<string, mixed>> $combinedApps */
        $combinedApps = array_combine(array_keys($apps), array_column($apps, 'template_load_priority'));

        $extensions = array_merge($combinedApps, $bundles);
        asort($extensions);

        // Replace app priorities with version strings after sorting
        // The sorted order is preserved but values change from int to string
        // This allows version-aware cache invalidation downstream
        foreach ($apps as $appName => ['version' => $version]) {
            $extensions[$appName] = $version;
        }

        // Chain with existing hierarchy
        return array_merge(
            $extensions,
            $namespaceHierarchy
        );
    }

    /**
     * @return array<mixed, array<string, mixed>>
     */
    private function getAppTemplateNamespaces(): array
    {
        return $this->connection->fetchAllAssociativeIndexed(
            'SELECT `app`.`name`, `app`.`version`, `app`.`template_load_priority`
             FROM `app`
             INNER JOIN `app_template` ON `app_template`.`app_id` = `app`.`id`
             WHERE `app`.`active` = 1 AND `app_template`.`active` = 1'
        );
    }
}
