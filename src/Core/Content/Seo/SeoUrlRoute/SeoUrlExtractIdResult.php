<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

/**
 * @deprecated tag:v6.3.0 - The update detection is moved to the corresponding indexer classes
 */
class SeoUrlExtractIdResult
{
    /**
     * @var array
     */
    private $ids;

    /**
     * @var bool
     */
    private $reindex;

    public function __construct(array $ids, bool $reindex = false)
    {
        $this->ids = array_unique($ids);
        $this->reindex = $reindex;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function mustReindex(): bool
    {
        return $this->reindex;
    }
}
