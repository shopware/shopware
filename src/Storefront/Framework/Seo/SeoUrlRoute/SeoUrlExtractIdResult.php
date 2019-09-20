<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlRoute;

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
        $this->ids = $ids;
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
