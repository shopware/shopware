<?php declare(strict_types=1);

namespace Shopware\Administration\Extension;

use Shopware\Api\Read\ExtensionRegistry;
use Shopware\Api\Read\ExtensionRegistryInterface;
use Shopware\Api\Read\FactoryExtensionInterface;

class AdministrationExtensionRegistry implements ExtensionRegistryInterface
{
    /**
     * @var array[]
     */
    private $extensions;

    /**
     * @var ExtensionRegistry
     */
    private $registry;

    public function __construct(array $extensions, ExtensionRegistry $registry)
    {
        $this->extensions = $extensions;
        $this->registry = $registry;
    }

    /**
     * @param string $bundle
     *
     * @return FactoryExtensionInterface[]
     */
    public function getExtensions(string $bundle): array
    {
        $extensions = [];
        if (array_key_exists($bundle, $this->extensions)) {
            $extensions = $this->extensions[$bundle];
        }

        return array_merge($extensions, $this->registry->getExtensions($bundle));
    }
}
