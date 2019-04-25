<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

/**
 * Contains all registered entity extensions in the system
 */
class ExtensionRegistry
{
    /**
     * @var EntityExtensionInterface[]
     */
    private $extensions;

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
