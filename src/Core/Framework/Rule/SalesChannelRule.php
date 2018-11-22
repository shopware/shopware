<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;

class SalesChannelRule extends Rule
{
    /**
     * @var string[]
     */
    protected $salesChannelIds;

    /**
     * @var string
     */
    protected $operator;

    public function match(RuleScope $scope): Match
    {
        $context = $scope->getContext();

        switch ($this->operator) {
            case self::OPERATOR_EQ:

                return new Match(
                    \in_array($context->getSourceContext()->getSalesChannelId(), $this->salesChannelIds, true),
                    ['SalesChannel not matched']
                );

            case self::OPERATOR_NEQ:

                return new Match(
                    !\in_array($context->getSourceContext()->getSalesChannelId(), $this->salesChannelIds, true),
                    ['SalesChannel not matched']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }
}
