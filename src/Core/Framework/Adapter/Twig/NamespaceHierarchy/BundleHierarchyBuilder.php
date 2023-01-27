<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpKernel\KernelInterface;

#[Package('core')]
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
        $bundles = [];

        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }

            $bundlePath = $bundle->getPath();

            $directory = $bundlePath . '/Resources/views';

            if (!file_exists($directory)) {
                continue;
            }

            $bundles[$bundle->getName()] = $bundle->getTemplatePriority();
        }

        $bundles = array_reverse($bundles);
        $apps = $this->getAppTemplateNamespaces();

        /** @var array $combinedApps */
        $combinedApps = array_combine(array_keys($apps), array_column($apps, 'template_load_priority'));

        $extensions = array_merge($combinedApps, $bundles);
        asort($extensions);

        foreach ($apps as $appName => ['version' => $version]) {
            $extensions[$appName] = $version;
        }

        return array_merge(
            $extensions,
            $namespaceHierarchy
        );
    }

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
