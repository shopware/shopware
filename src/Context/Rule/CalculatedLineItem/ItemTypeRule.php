<?php declare(strict_types=1);

namespace Shopware\Context\Rule\CalculatedLineItem;

use Shopware\Context\Exception\InvalidMatchContext;
use Shopware\Context\MatchContext\CalculatedLineItemMatchContext;
use Shopware\Context\MatchContext\RuleMatchContext;
use Shopware\Context\Rule\Match;
use Shopware\Context\Rule\Rule;

class ItemTypeRule extends Rule
{
    /**
     * @var string
     */
    protected $type;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    /**
     * Validate the current rule and returns a reason object which contains defines if the rule match and if not why not
     *
     * @throws InvalidMatchContext
     */
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
