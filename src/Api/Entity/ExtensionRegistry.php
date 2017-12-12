<?php declare(strict_types=1);

namespace Shopware\Api\Entity;

class ExtensionRegistry
{
    /**
     * @var EntityExtensionInterface[]
     */
    protected $extensions;

    public function __construct(iterable $extensions)
    {
        $this->extensions = $extensions;
    }

    /**
     * @return EntityExtensionInterface[]|iterable
     */
    public function getExtensions(): iterable
    {
        return $this->extensions;
    }
}
