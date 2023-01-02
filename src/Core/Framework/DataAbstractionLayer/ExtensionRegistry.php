<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 * Contains all registered entity extensions in the system
 */
#[Package('core')]
class ExtensionRegistry
{
    /**
     * @var EntityExtension[]|iterable
     */
    private $extensions;

    /**
     * @internal
     */
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
