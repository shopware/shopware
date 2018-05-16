<?php declare(strict_types=1);

namespace Shopware\Checkout\Rule\Specification\Scope;

use Shopware\Application\Context\Struct\StorefrontContext;

class StorefrontScope extends RuleScope
{
    /**
     * @var StorefrontContext
     */
    protected $context;

    public function __construct(StorefrontContext $context)
    {
        $this->context = $context;
    }

    public function getContext(): StorefrontContext
    {
        return $this->context;
    }
}
