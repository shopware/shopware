<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Api\Entity\Entity;

class ProductSearchKeywordBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $keyword;

    /**
     * @var string
     */
    protected $shopUuid;

    /**
     * @var string
     */
    protected $productUuid;

    /**
     * @var float
     */
    protected $ranking;

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    public function setKeyword(string $keyword): void
    {
        $this->keyword = $keyword;
    }

    public function getShopUuid(): string
    {
        return $this->shopUuid;
    }

    public function setShopUuid(string $shopUuid): void
    {
        $this->shopUuid = $shopUuid;
    }

    public function getProductUuid(): string
    {
        return $this->productUuid;
    }

    public function setProductUuid(string $productUuid): void
    {
        $this->productUuid = $productUuid;
    }

    public function getRanking(): float
    {
        return $this->ranking;
    }

    public function setRanking(float $ranking): void
    {
        $this->ranking = $ranking;
    }
}
