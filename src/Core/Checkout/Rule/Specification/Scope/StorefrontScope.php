<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Rule\Specification\Scope;

use Shopware\Core\Checkout\CustomerContext;

class StorefrontScope extends RuleScope
{
    /**
     * @var CustomerContext
     */
    protected $context;

    public function __construct(CustomerContext $context)
    {
        $this->context = $context;
    }

    public function getContext(): CustomerContext
    {
        return $this->context;
    }
}
