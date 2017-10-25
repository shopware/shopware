<?php declare(strict_types=1);

namespace Shopware\Framework\Read;

interface ExtensionRegistryInterface
{
    /**
     * @param string $bundle
     *
     * @return ExtensionInterface[]
     */
    public function getExtensions(string $bundle): array;
}
