<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * Keyed by the sales channel domain URL with a trailing slash (e.g. `https://example.com/de/`),
 * which matches the normalized request URL the RequestTransformer uses for lookups.
 *
 * @extends Collection<DomainStruct>
 */
#[Package('framework')]
class DomainCollection extends Collection
{
    /**
     * @param array<string, array<string, string|null>> $rows
     */
    public static function fromArray(array $rows): self
    {
        $collection = new self();

        foreach ($rows as $row) {
            $collection->add(DomainStruct::fromArray($row));
        }

        return $collection;
    }

    /**
     * @param DomainStruct $element
     */
    public function add($element): void
    {
        $this->set($element->url . '/', $element);
    }

    public function getApiAlias(): string
    {
        return 'storefront_domain_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return DomainStruct::class;
    }
}
