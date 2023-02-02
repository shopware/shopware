<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

#[Package('checkout')]
class DeliveryTime extends Struct
{
    /**
     * @var string
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

    public static function createFromEntity(DeliveryTimeEntity $entity): self
    {
        $self = new self();
        $self->setName((string) $entity->getTranslation('name'));
        $self->setUnit($entity->getUnit());
        $self->setMax($entity->getMax());
        $self->setMin($entity->getMin());

        return $self;
    }

    public function getName(): string
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

    public function getApiAlias(): string
    {
        return 'cart_delivery_time';
    }
}
