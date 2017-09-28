<?php declare(strict_types=1);

namespace Shopware\Media\Searcher;

use Shopware\Media\Struct\MediaBasicCollection;
use Shopware\Search\SearchResultInterface;

class MediaSearchResult extends MediaBasicCollection implements SearchResultInterface
{
    /**
     * @var int
     */
    protected $total = 0;

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }
}
