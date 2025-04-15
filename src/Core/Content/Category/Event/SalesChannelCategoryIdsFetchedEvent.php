<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Content\Category\CategoryException;
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
     * @param list<string> $categoryIds Category ids **must** be provided as hex strings
     */
    public function __construct(
        array $categoryIds,
        private readonly SalesChannelContext $context
    ) {
        foreach ($categoryIds as $categoryId) {
            $this->checkValidCategoryIdOrThrow($categoryId);
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
        $this->checkValidCategoryIdOrThrow($categoryId);
        return \array_key_exists($categoryId, $this->categoryIds);
    }

    /**
     * @return list<string>
     */
    public function getFilteredIds(): array
    {
        return \array_values($this->filteredIds);
    }

    /**
     * @param string $categoryId Category ID to remove from IDs as hex string
     */
    public function filterId(string $categoryId): void
    {
        $this->checkValidCategoryIdOrThrow($categoryId);
        $this->filteredIds[$categoryId] = $categoryId;
        unset($this->categoryIds[$categoryId]);
    }

    /**
     * @param string $categoryId Category ID to check for being removed from IDs as hex string
     */
    public function isFiltered(string $categoryId): bool
    {
        $this->checkValidCategoryIdOrThrow($categoryId);
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

    private function checkValidCategoryIdOrThrow(string $categoryId): void
    {
        if (Uuid::isValid($categoryId)) {
            return;
        }
        throw CategoryException::categoryIdIsNotValidHex($categoryId);
    }
}
