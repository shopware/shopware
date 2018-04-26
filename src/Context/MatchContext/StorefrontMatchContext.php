<?php declare(strict_types=1);

namespace Shopware\Context\MatchContext;

use Shopware\Context\Struct\StorefrontContext;

class StorefrontMatchContext extends RuleMatchContext
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
