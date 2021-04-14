<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Bundle;
use Symfony\Component\HttpKernel\KernelInterface;

class BundleHierarchyBuilder implements TemplateNamespaceHierarchyBuilderInterface
{
    private KernelInterface $kernel;

    private Connection $connection;

    public function __construct(KernelInterface $kernel, Connection $connection)
    {
        $this->kernel = $kernel;
        $this->connection = $connection;
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

            // bundle or plugin version unknown at this point
            $bundles[$bundle->getName()] = 1;
        }

        $bundles = array_reverse($bundles);

        return array_merge(
            $this->getAppTemplateNamespaces(),
            $bundles,
            $namespaceHierarchy
        );
    }

    private function getAppTemplateNamespaces(): array
    {
        return $this->connection->fetchAllKeyValue(
            'SELECT `app`.`name`, `app`.`version`
             FROM `app`
             INNER JOIN `app_template` ON `app_template`.`app_id` = `app`.`id`
             WHERE `app`.`active` = 1 AND `app_template`.`active` = 1'
        );
    }
}
