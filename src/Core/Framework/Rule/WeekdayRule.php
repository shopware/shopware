<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

/**
 * @package business-ops
 */
class WeekdayRule extends Rule
{
    protected string $operator;

    protected ?int $dayOfWeek;

    /**
     * @internal
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?int $dayOfWeek = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->dayOfWeek = $dayOfWeek;
    }

    public function getName(): string
    {
        return 'dayOfWeek';
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
