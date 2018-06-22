<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword;

use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Language\LanguageStruct;

class ProductSearchKeywordStruct extends Entity
{
    /**
     * @var string
     */
    protected $keyword;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var float
     */
    protected $ranking;

    /**
     * @var ProductStruct|null
     */
    protected $product;

    /**
     * @var LanguageStruct|null
     */
    protected $language;

    public function getLanguage(): ?LanguageStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageStruct $language): void
    {
        $this->language = $language;
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    public function setKeyword(string $keyword): void
    {
        $this->keyword = $keyword;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getRanking(): float
    {
        return $this->ranking;
    }

    public function setRanking(float $ranking): void
    {
        $this->ranking = $ranking;
    }

    public function getProduct(): ?ProductStruct
    {
        return $this->product;
    }

    public function setProduct(ProductStruct $product): void
    {
        $this->product = $product;
    }
}
