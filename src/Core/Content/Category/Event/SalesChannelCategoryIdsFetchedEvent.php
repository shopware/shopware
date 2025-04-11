<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Category\Exception\InvalidCategoryIdException;
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
            \assert(\is_string($categoryId));
            $uuid = $this->toHexIfBin($categoryId);
            $this->categoryIds[$uuid] = $uuid;
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
        return \array_key_exists($this->toHexIfBin($categoryId), $this->categoryIds);
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
        $uuid = $this->toHexIfBin($categoryId);
        $this->filteredIds[$uuid] = $uuid;
        unset($this->categoryIds[$uuid]);
    }

    public function isFiltered(string $categoryId): bool
    {
        return \array_key_exists($this->toHexIfBin($categoryId), $this->filteredIds);
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    private function toHexIfBin(string $uuid): string
    {
        if (\strlen($uuid) === 16) {
            $convertedUuid = Uuid::fromBytesToHex($uuid);
        } elseif (Uuid::isValid($uuid)) {
            $convertedUuid = $uuid;
        } else {
            throw CategoryException::invalidCategoryId($uuid);
        }

        return $convertedUuid;
    }
}
