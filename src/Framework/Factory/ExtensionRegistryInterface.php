<?php

namespace Shopware\Framework\Factory;

interface ExtensionRegistryInterface
{
    /**
     * @param string $bundle
     *
     * @return ExtensionInterface[]
     */
    public function getExtensions(string $bundle): array;
}
