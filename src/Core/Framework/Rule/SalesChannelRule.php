<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

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

    public function __construct()
    {
        $this->operator = self::OPERATOR_EQ;
    }

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
                    ['SalesChannel matched']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }

    public static function getConstraints(): array
    {
        return [
            'salesChannelIds' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }

    public static function getName(): string
    {
        return 'sales_channel';
    }
}
