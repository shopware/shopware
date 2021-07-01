<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @codeCoverageIgnore
 *
 * @method LicenseDomainStruct[]    getIterator()
 * @method LicenseDomainStruct[]    getElements()
 * @method LicenseDomainStruct|null get(string $key)
 * @method LicenseDomainStruct|null first()
 * @method LicenseDomainStruct|null last()
 */
class LicenseDomainCollection extends Collection
{
    public function getExpectedClass(): ?string
    {
        return LicenseDomainStruct::class;
    }

    public function add($element): void
    {
        $this->validateType($element);

        $this->elements[$element->getDomain()] = $element;
    }

    public function set($key, $element): void
    {
        parent::set($element->getDomain(), $element);
    }

    public function getApiAlias(): string
    {
        return 'store_license_domain_collection';
    }
}
