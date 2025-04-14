<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event that is triggered when category ids are fetched for a sales channel without using the DAL.
 */
#[Package('discovery')]
final class SalesChannelCategoryIdsFetchedEvent extends Event implements ShopwareEvent
{
    final public const NAME = 'sales_channel.category.keys.fetched';

    /**
     * @var array<string, string>
     */
    private array $filteredIds = [];

    /**
     * @var array<string, string>
     */
    private array $categoryIds = [];

    /**
     * @param list<string> $categoryIds
     */
    public function __construct(
        array $categoryIds,
        private readonly SalesChannelContext $context
    ) {
        foreach ($categoryIds as $categoryId) {
            \assert(Uuid::isValid($categoryId));
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

    public function hasId(string $categoryId): bool
    {
        return \array_key_exists($categoryId, $this->categoryIds);
    }

    /**
     * @return list<string>
     */
    public function getFilteredIds(): array
    {
        return \array_values($this->filteredIds);
    }

    public function filterId(string $categoryId): void
    {
        \assert(Uuid::isValid($categoryId));
        $this->filteredIds[$categoryId] = $categoryId;
        unset($this->categoryIds[$categoryId]);
    }

    public function isFiltered(string $categoryId): bool
    {
        return \array_key_exists($categoryId, $this->filteredIds);
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
