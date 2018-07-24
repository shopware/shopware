<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Cart\CollectorInterface;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Struct\StructCollection;

class Enrichment
{
    /**
     * @var CollectorInterface[]
     */
    private $collectors;

    public function __construct(iterable $collectors)
    {
        $this->collectors = $collectors;
    }

    public function enrich(Cart $cart, CheckoutContext $context): Cart
    {
        $definitions = new StructCollection();
        foreach ($this->collectors as $collector) {
            $collector->prepare($definitions, $cart, $context);
        }

        $data = new StructCollection();
        foreach ($this->collectors as $collector) {
            $collector->collect($definitions, $data, $cart, $context);
        }

        foreach ($this->collectors as $collector) {
            $collector->enrich($data, $cart, $context);
        }

        return $cart;
    }
}
