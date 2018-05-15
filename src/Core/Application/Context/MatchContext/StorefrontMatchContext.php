<?php declare(strict_types=1);

namespace Shopware\Application\Context\MatchContext;

use Shopware\Application\Context\Struct\StorefrontContext;

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
