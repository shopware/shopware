<?php declare(strict_types=1);

namespace Shopware\Nexus\Extension;

use Shopware\Framework\Factory\ExtensionRegistry;
use Shopware\Framework\Factory\ExtensionRegistryInterface;

class NexusExtensionRegistry implements ExtensionRegistryInterface
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
     * @return ExtensionInterface[]
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
