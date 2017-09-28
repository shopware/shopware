<?php declare(strict_types=1);

namespace Shopware\Album\Searcher;

use Shopware\Album\Struct\AlbumBasicCollection;
use Shopware\Search\SearchResultInterface;

class AlbumSearchResult extends AlbumBasicCollection implements SearchResultInterface
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
