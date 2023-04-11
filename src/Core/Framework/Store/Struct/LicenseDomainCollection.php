<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @codeCoverageIgnore
 *
 * @extends Collection<LicenseDomainStruct>
 */
#[Package('merchant-services')]
class LicenseDomainCollection extends Collection
{
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

    protected function getExpectedClass(): ?string
    {
        return LicenseDomainStruct::class;
    }
}
