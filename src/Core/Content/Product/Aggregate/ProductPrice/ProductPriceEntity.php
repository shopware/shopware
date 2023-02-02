<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPrice;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceRuleEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductPriceEntity extends PriceRuleEntity
{
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var int
     */
    protected $quantityStart;

    /**
     * @var int|null
     */
    protected $quantityEnd;

    /**
     * @var ProductEntity|null
     */
    protected $product;

    /**
     * @var RuleEntity|null
     */
    protected $rule;

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getRule(): ?RuleEntity
    {
        return $this->rule;
    }

    public function setRule(RuleEntity $rule): void
    {
        $this->rule = $rule;
    }

    public function getQuantityStart(): int
    {
        return $this->quantityStart;
    }

    public function setQuantityStart(int $quantityStart): void
    {
        $this->quantityStart = $quantityStart;
    }

    public function getQuantityEnd(): ?int
    {
        return $this->quantityEnd;
    }

    public function setQuantityEnd(?int $quantityEnd): void
    {
        $this->quantityEnd = $quantityEnd;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }
}
