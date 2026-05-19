<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event that is triggered when category ids are fetched for a sales channel without using the DAL.
 */
#[Package('discovery')]
final class SalesChannelCategoryIdsFetchedEvent extends Event implements ShopwareEvent
{
    /**
     * @var array<string, string>
     */
    private array $categoryIds = [];

    /**
     * @param list<string> $categoryIds Category ids **must** be provided as hex strings
     */
    public function __construct(
        array $categoryIds,
        private readonly SalesChannelContext $context
    ) {
        foreach ($categoryIds as $categoryId) {
            $this->categoryIds[$categoryId] = $categoryId;
        }
    }

    /**
     * @return list<string>
     */
    public function getIds(): array
    {
        return \array_values($this->categoryIds);
    }

    /**
     * @param string $categoryId Category ID to check as hex string
     */
    public function hasId(string $categoryId): bool
    {
        return \array_key_exists($categoryId, $this->categoryIds);
    }

    /**
     * @param string $categoryId Category ID to remove from IDs as hex string
     */
    public function filterId(string $categoryId): void
    {
        unset($this->categoryIds[$categoryId]);
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }
}
