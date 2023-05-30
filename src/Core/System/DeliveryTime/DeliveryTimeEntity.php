<?php declare(strict_types=1);

namespace Shopware\Core\System\DeliveryTime;

use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\DeliveryTime\Aggregate\DeliveryTimeTranslation\DeliveryTimeTranslationCollection;

#[Package('customer-order')]
class DeliveryTimeEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;
    final public const DELIVERY_TIME_HOUR = 'hour';
    final public const DELIVERY_TIME_DAY = 'day';
    final public const DELIVERY_TIME_WEEK = 'week';
    final public const DELIVERY_TIME_MONTH = 'month';
    final public const DELIVERY_TIME_YEAR = 'year';

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var int
     */
    protected $min;

    /**
     * @var int
     */
    protected $max;

    /**
     * @var string
     */
    protected $unit;

    /**
     * @var ShippingMethodCollection|null
     */
    protected $shippingMethods;

    /**
     * @var DeliveryTimeTranslationCollection|null
     */
    protected $translations;

    /**
     * @var ProductCollection|null
     */
    protected $products;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getMin(): int
    {
        return $this->min;
    }

    public function setMin(int $min): void
    {
        $this->min = $min;
    }

    public function getMax(): int
    {
        return $this->max;
    }

    public function setMax(int $max): void
    {
        $this->max = $max;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): void
    {
        $this->unit = $unit;
    }

    public function getShippingMethods(): ?ShippingMethodCollection
    {
        return $this->shippingMethods;
    }

    public function setShippingMethods(ShippingMethodCollection $shippingMethods): void
    {
        $this->shippingMethods = $shippingMethods;
    }

    /**
     * @return DeliveryTimeTranslationCollection|null
     */
    public function getTranslations(): ?EntityCollection
    {
        return $this->translations;
    }

    /**
     * @param DeliveryTimeTranslationCollection $translations
     */
    public function setTranslations(EntityCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getProducts(): ?ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
    }
}
