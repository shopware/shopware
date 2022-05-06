<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
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
