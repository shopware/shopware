<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('inventory')]
class AnalyzedKeyword extends Struct
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $keyword;

    /**
     * @var float
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $ranking;

    public function __construct(
        string $keyword,
        float $ranking
    ) {
        $this->keyword = $keyword;
        $this->ranking = $ranking;
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    public function getRanking(): float
    {
        return $this->ranking;
    }

    public function setRanking(float $ranking): void
    {
        $this->ranking = $ranking;
    }

    public function getApiAlias(): string
    {
        return 'product_search_keyword_analyzed';
    }
}
