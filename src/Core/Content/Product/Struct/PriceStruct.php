<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Struct;

use Shopware\Core\Framework\Struct\Struct;

class PriceStruct extends Struct
{
    /**
     * @var float
     */
    protected $net;

    /**
     * @var float
     */
    protected $gross;

    public function __construct(float $net, float $gross)
    {
        $this->net = $net;
        $this->gross = $gross;
    }

    public function getNet(): float
    {
        return $this->net;
    }

    public function setNet(float $net): void
    {
        $this->net = $net;
    }

    public function getGross(): float
    {
        return $this->gross;
    }

    public function setGross(float $gross): void
    {
        $this->gross = $gross;
    }

    public function add(self $price)
    {
        $this->gross += $price->getGross();
        $this->net += $price->getNet();
    }
}
