<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class WeekdayRule extends Rule
{
    /**
     * @var string
     */
    protected $operator;

    /**
     * @var int
     */
    protected $dayOfWeek;

    public function __construct()
    {
        $this->operator = self::OPERATOR_EQ;
    }

    public function getName(): string
    {
        return 'dayOfWeek';
    }

    public function match(RuleScope $scope): Match
    {
        $todaysDayOfWeek = (int) \date('N');

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return new Match($todaysDayOfWeek === (int) $this->dayOfWeek, [
                    \sprintf('Today is not the selected day of week. Today is %s',
                        date('l')
                    ),
                ]);
            case self::OPERATOR_NEQ:
                return new Match($todaysDayOfWeek !== (int) $this->dayOfWeek, [
                    \sprintf('Today is the selected day of week. Today is %s',
                        date('l')
                    ),
                ]);
            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }

    public function getConstraints(): array
    {
        return [
            'operator' => [
                new Choice([
                    self::OPERATOR_EQ,
                    self::OPERATOR_NEQ,
                ]),
            ],
            'dayOfWeek' => [new NotBlank(), new Range(['min' => 1, 'max' => 7])],
        ];
    }
}
