<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\EntityProtection;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method EntityProtection[]    getIterator()
 * @method EntityProtection[]    getElements()
 * @method EntityProtection|null get(string $key)
 * @method EntityProtection|null first()
 * @method EntityProtection|null last()
 */
class EntityProtectionCollection extends Collection
{
    /**
     * @param EntityProtection $element
     */
    public function add($element): void
    {
        $this->set(get_class($element), $element);
    }

    /**
     * @param string|int       $key
     * @param EntityProtection $element
     */
    public function set($key, $element): void
    {
        parent::set(get_class($element), $element);
    }

    public function getApiAlias(): string
    {
        return 'dal_protection_collection';
    }
}
