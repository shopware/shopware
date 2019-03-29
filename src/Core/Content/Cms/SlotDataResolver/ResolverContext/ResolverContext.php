<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext;

use Shopware\Core\Checkout\CheckoutContext;

class ResolverContext
{
    /**
     * @var CheckoutContext
     */
    protected $context;

    public function __construct(CheckoutContext $context)
    {
        $this->context = $context;
    }

    public function getCheckoutContext(): CheckoutContext
    {
        return $this->context;
    }
}
