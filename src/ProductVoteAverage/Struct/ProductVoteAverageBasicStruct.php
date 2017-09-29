<?php declare(strict_types=1);

namespace Shopware\ProductVoteAverage\Struct;

use Shopware\Framework\Struct\Struct;

class ProductVoteAverageBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $productUuid;

    /**
     * @var string
     */
    protected $shopUuid;

    /**
     * @var float
     */
    protected $average;

    /**
     * @var int
     */
    protected $total;

    /**
     * @var int
     */
    protected $fivePointCount;

    /**
     * @var int
     */
    protected $fourPointCount;

    /**
     * @var int
     */
    protected $threePointCount;

    /**
     * @var int
     */
    protected $twoPointCount;

    /**
     * @var int
     */
    protected $onePointCount;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getProductUuid(): string
    {
        return $this->productUuid;
    }

    public function setProductUuid(string $productUuid): void
    {
        $this->productUuid = $productUuid;
    }

    public function getShopUuid(): string
    {
        return $this->shopUuid;
    }

    public function setShopUuid(string $shopUuid): void
    {
        $this->shopUuid = $shopUuid;
    }

    public function getAverage(): float
    {
        return $this->average;
    }

    public function setAverage(float $average): void
    {
        $this->average = $average;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    public function getFivePointCount(): int
    {
        return $this->fivePointCount;
    }

    public function setFivePointCount(int $fivePointCount): void
    {
        $this->fivePointCount = $fivePointCount;
    }

    public function getFourPointCount(): int
    {
        return $this->fourPointCount;
    }

    public function setFourPointCount(int $fourPointCount): void
    {
        $this->fourPointCount = $fourPointCount;
    }

    public function getThreePointCount(): int
    {
        return $this->threePointCount;
    }

    public function setThreePointCount(int $threePointCount): void
    {
        $this->threePointCount = $threePointCount;
    }

    public function getTwoPointCount(): int
    {
        return $this->twoPointCount;
    }

    public function setTwoPointCount(int $twoPointCount): void
    {
        $this->twoPointCount = $twoPointCount;
    }

    public function getOnePointCount(): int
    {
        return $this->onePointCount;
    }

    public function setOnePointCount(int $onePointCount): void
    {
        $this->onePointCount = $onePointCount;
    }
}
