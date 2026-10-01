<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\DatePoint;

#[Package('checkout')]
class DeliveryDate extends Struct
{
    protected \DateTimeImmutable $earliest;

    protected \DateTimeImmutable $latest;

    public function __construct(
        \DateTimeInterface $earliest,
        \DateTimeInterface $latest
    ) {
        $earliest = new \DateTimeImmutable($earliest->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        $latest = new \DateTimeImmutable($latest->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        $this->earliest = $earliest->setTime(16, 0);
        $this->latest = $latest->setTime(16, 0);
    }

    public static function createFromDeliveryTime(DeliveryTime $deliveryTime): self
    {
        return self::createFromDeliveryTimeAt($deliveryTime, Clock::get()->now());
    }

    public static function createFromDeliveryTimeAt(DeliveryTime $deliveryTime, \DateTimeInterface $base): self
    {
        return match ($deliveryTime->getUnit()) {
            DeliveryTimeEntity::DELIVERY_TIME_HOUR => new self(
                self::create('PT' . $deliveryTime->getMin() . 'H', $base),
                self::create('PT' . $deliveryTime->getMax() . 'H', $base)
            ),
            DeliveryTimeEntity::DELIVERY_TIME_DAY => new self(
                self::create('P' . $deliveryTime->getMin() . 'D', $base),
                self::create('P' . $deliveryTime->getMax() . 'D', $base)
            ),
            DeliveryTimeEntity::DELIVERY_TIME_WEEK => new self(
                self::create('P' . $deliveryTime->getMin() . 'W', $base),
                self::create('P' . $deliveryTime->getMax() . 'W', $base)
            ),
            DeliveryTimeEntity::DELIVERY_TIME_MONTH => new self(
                self::create('P' . $deliveryTime->getMin() . 'M', $base),
                self::create('P' . $deliveryTime->getMax() . 'M', $base)
            ),
            DeliveryTimeEntity::DELIVERY_TIME_YEAR => new self(
                self::create('P' . $deliveryTime->getMin() . 'Y', $base),
                self::create('P' . $deliveryTime->getMax() . 'Y', $base)
            ),
            default => throw CartException::deliveryDateNotSupportedUnit($deliveryTime->getUnit()),
        };
    }

    public function getEarliest(): \DateTimeImmutable
    {
        return $this->earliest;
    }

    public function getLatest(): \DateTimeImmutable
    {
        return $this->latest;
    }

    public function add(\DateInterval $interval): self
    {
        return new DeliveryDate(
            $this->earliest->add($interval),
            $this->latest->add($interval)
        );
    }

    public function getApiAlias(): string
    {
        return 'cart_delivery_date';
    }

    private static function create(string $interval, \DateTimeInterface $base): \DateTimeImmutable
    {
        return DatePoint::createFromInterface($base)->add(new \DateInterval($interval));
    }
}
