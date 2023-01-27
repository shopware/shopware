<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

#[Package('business-ops')]
class WeekdayRule extends Rule
{
    final public const RULE_NAME = 'dayOfWeek';

    /**
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?int $dayOfWeek = null
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        $todaysDayOfWeek = (int) $scope->getCurrentTime()->format('N');

        return RuleComparison::numeric($todaysDayOfWeek, $this->dayOfWeek, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'operator' => RuleConstraints::stringOperators(false),
            'dayOfWeek' => [new NotBlank(), new Range(['min' => 1, 'max' => 7])],
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->selectField('dayOfWeek', range(1, 7));
    }
}
