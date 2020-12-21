<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
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
        $todaysDayOfWeek = (int) date('N');

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return $todaysDayOfWeek === (int) $this->dayOfWeek;

            case self::OPERATOR_NEQ:
                return $todaysDayOfWeek !== (int) $this->dayOfWeek;

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        return [
            'operator' => [
                new NotBlank(),
                new Choice([
                    self::OPERATOR_EQ,
                    self::OPERATOR_NEQ,
                ]),
            ],
            'dayOfWeek' => [new NotBlank(), new Range(['min' => 1, 'max' => 7])],
        ];
    }
}
