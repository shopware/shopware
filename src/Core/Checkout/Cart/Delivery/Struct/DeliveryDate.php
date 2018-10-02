<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Framework\Struct\Struct;

class DeliveryDate extends Struct
{
    /**
     * @var \DateTime
     */
    protected $earliest;

    /**
     * @var \DateTime
     */
    protected $latest;

    public function __construct(
        \DateTime $earliest,
        \DateTime $latest
    ) {
        $earliest->setTime(16, 0);
        $latest->setTime(16, 0);

        $this->earliest = $earliest;
        $this->latest = $latest;
    }

    /**
     * @return \DateTime
     */
    public function getEarliest(): \DateTime
    {
        return $this->earliest;
    }

    /**
     * @return \DateTime
     */
    public function getLatest(): \DateTime
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
