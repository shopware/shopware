<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Struct\Struct;

abstract class GenericPage extends Struct
{
    /**
     * @var CheckoutContext
     */
    protected $context;

    public function __construct(CheckoutContext $context)
    {
        $this->context = $context;
    }

    public function getContext(): CheckoutContext
    {
        return $this->context;
    }

    public function setContext(CheckoutContext $context): void
    {
        $this->context = $context;
    }
}
