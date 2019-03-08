<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Struct;

class DeliveryDate extends Struct
{
    /**
     * @var \DateTimeImmutable
     */
    protected $earliest;

    /**
     * @var \DateTimeImmutable
     */
    protected $latest;

    public function __construct(
        \DateTimeInterface $earliest,
        \DateTimeInterface $latest
    ) {
        $earliest = new \DateTimeImmutable($earliest->format(Defaults::DATE_FORMAT));
        $latest = new \DateTimeImmutable($latest->format(Defaults::DATE_FORMAT));

        $this->earliest = $earliest->setTime(16, 0);
        $this->latest = $latest->setTime(16, 0);
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
}
