<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class ProductListingResolvePreviewEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    /**
     * @param array<string> $mapping
     */
    public function __construct(
        protected SalesChannelContext $context,
        protected Criteria $criteria,
        protected array $mapping,
        protected bool $hasOptionFilter
    ) {
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    /**
     * @return array<string>
     */
    public function getMapping(): array
    {
        return $this->mapping;
    }

    public function replace(string $originalId, string $newId): void
    {
        if (!\array_key_exists($originalId, $this->mapping)) {
            throw ProductException::originalIdNotFound($originalId);
        }

        $this->mapping[$originalId] = $newId;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function hasOptionFilter(): bool
    {
        return $this->hasOptionFilter;
    }
}
