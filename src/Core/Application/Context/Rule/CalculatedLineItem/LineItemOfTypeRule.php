<?php declare(strict_types=1);

namespace Shopware\Application\Context\Rule\CalculatedLineItem;

use Shopware\Application\Context\MatchContext\CalculatedLineItemMatchContext;
use Shopware\Application\Context\MatchContext\RuleMatchContext;
use Shopware\Application\Context\Rule\Match;
use Shopware\Application\Context\Rule\Rule;

class LineItemOfTypeRule extends Rule
{
    /**
     * @var string
     */
    protected $type;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public function match(
        RuleMatchContext $matchContext
    ): Match {
        if (!$matchContext instanceof CalculatedLineItemMatchContext) {
            return new Match(
                false,
                ['Invalid Match Context. CalculatedLineItemMatchContext expected']
            );
        }

        return new Match(
            $matchContext->getCalculatedLineItem()->getType() == $this->type,
            ['LineItem type does not match']
        );
    }
}
