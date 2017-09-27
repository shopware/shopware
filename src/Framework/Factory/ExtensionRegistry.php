<?php

namespace Shopware\Framework\Factory;

class ExtensionRegistry implements ExtensionRegistryInterface
{
    /**
     * @var array[]
     */
    private $extensions;

    public function __construct(array $extensions)
    {
        $this->extensions = $extensions;
    }

    /**
     * @param string $bundle
     *
     * @return ExtensionInterface[]
     */
    public function getExtensions(string $bundle): array
    {
        if (array_key_exists($bundle, $this->extensions)) {
            return $this->extensions[$bundle];
        }

        return [];
    }
}
