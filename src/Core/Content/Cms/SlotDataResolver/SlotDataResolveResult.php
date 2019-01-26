<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SlotDataResolver;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class SlotDataResolveResult
{
    protected $searchResults = [];

    public function add(string $key, EntitySearchResult $entitySearchResult): void
    {
        $this->searchResults[$key] = $entitySearchResult;
    }

    public function get(string $key): ?EntitySearchResult
    {
        return $this->searchResults[$key] ?? null;
    }
}
