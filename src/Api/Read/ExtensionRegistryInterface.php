<?php declare(strict_types=1);

namespace Shopware\Api\Read;

interface ExtensionRegistryInterface
{
    /**
     * @param string $bundle
     *
     * @return FactoryExtensionInterface[]
     */
    public function getExtensions(string $bundle): array;
}
