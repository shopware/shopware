<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

/**
 * Contains all registered entity extensions in the system
 */
class ExtensionRegistry
{
    /**
     * @var EntityExtension[]
     */
    private $extensions;

    public function __construct(iterable $extensions)
    {
        $this->extensions = $extensions;
    }

    /**
     * @return EntityExtension[]|iterable
     */
    public function getExtensions(): iterable
    {
        return $this->extensions;
    }
}
