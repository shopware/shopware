<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Pricing;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\Struct\Struct;

class CalculatedListingPrice extends Struct
{
    /**
     * @var CalculatedPrice
     */
    protected $from;

    /**
     * @var CalculatedPrice
     */
    protected $to;

    public function __construct(CalculatedPrice $from, CalculatedPrice $to)
    {
        $this->from = $from;
        $this->to = $to;
    }

    public function getFrom(): CalculatedPrice
    {
        return $this->from;
    }

    public function setFrom(CalculatedPrice $from): void
    {
        $this->from = $from;
    }

    public function getTo(): CalculatedPrice
    {
        return $this->to;
    }

    public function setTo(CalculatedPrice $to): void
    {
        $this->to = $to;
    }

    public function hasRange(): bool
    {
        return $this->getFrom()->getTotalPrice() !== $this->getTo()->getTotalPrice();
    }
}
