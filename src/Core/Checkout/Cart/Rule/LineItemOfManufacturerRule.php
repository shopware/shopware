<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class LineItemOfManufacturerRule extends Rule
{
    /**
     * @var string[]
     */
    protected array $manufacturerIds;

    protected string $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, array $manufacturerIds = [])
    {
        parent::__construct();

        $this->manufacturerIds = $manufacturerIds;
        $this->operator = $operator;
    }

    public function getName(): string
    {
        return 'cartLineItemOfManufacturer';
    }

    /**
     * @throws UnsupportedOperatorException
     * @throws PayloadKeyNotFoundException
     */
    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchesOneOfManufacturers($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->matchesOneOfManufacturers($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => [
                new NotBlank(),
                new Choice([
                    self::OPERATOR_EQ,
                    self::OPERATOR_NEQ,
                    self::OPERATOR_EMPTY,
                ]),
            ],
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['manufacturerIds'] = [new NotBlank(), new ArrayOfUuid()];

        return $constraints;
    }

    /**
     * @throws UnsupportedOperatorException
     * @throws PayloadKeyNotFoundException
     */
    private function matchesOneOfManufacturers(LineItem $lineItem): bool
    {
        $manufacturerId = (string) $lineItem->getPayloadValue('manufacturerId');

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return \in_array($manufacturerId, $this->manufacturerIds, true);

            case self::OPERATOR_NEQ:
                return !\in_array($manufacturerId, $this->manufacturerIds, true);

            case self::OPERATOR_EMPTY:
                return empty($manufacturerId);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }
}
