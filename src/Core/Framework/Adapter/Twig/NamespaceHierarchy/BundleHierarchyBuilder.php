<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy;

use Shopware\Core\Framework\Bundle;
use Symfony\Component\HttpKernel\KernelInterface;

class BundleHierarchyBuilder implements TemplateNamespaceHierarchyBuilderInterface
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function buildNamespaceHierarchy(array $namespaceHierarchy): array
    {
        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }

            $bundlePath = $bundle->getPath();

            $directory = $bundlePath . '/Resources/views';

            if (!file_exists($directory)) {
                continue;
            }

            array_unshift($namespaceHierarchy, $bundle->getName());

            $namespaceHierarchy = array_values(array_unique($namespaceHierarchy));
        }

        return $namespaceHierarchy;
    }
}
