<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\EntityProtection;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<EntityProtection>
 */
#[Package('core')]
class EntityProtectionCollection extends Collection
{
    /**
     * @param EntityProtection $element
     */
    public function add($element): void
    {
        $this->set($element::class, $element);
    }

    /**
     * @param string|int       $key
     * @param EntityProtection $element
     */
    public function set($key, $element): void
    {
        parent::set($element::class, $element);
    }

    public function getApiAlias(): string
    {
        return 'dal_protection_collection';
    }
}
