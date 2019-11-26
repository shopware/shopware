<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
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

    public function __construct(string $operator = self::OPERATOR_EQ, ?array $salesChannelIds = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->salesChannelIds = $salesChannelIds;
    }

    public function match(RuleScope $scope): bool
    {
        $salesChannelId = $scope->getSalesChannelContext()->getSalesChannel()->getId();

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return \in_array($salesChannelId, $this->salesChannelIds, true);

            case self::OPERATOR_NEQ:
                return !\in_array($salesChannelId, $this->salesChannelIds, true);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        return [
            'salesChannelIds' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [new NotBlank(), new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }

    public function getName(): string
    {
        return 'salesChannel';
    }
}
