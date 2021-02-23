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

class LineItemTaxationRule extends Rule
{
    /**
     * @var string[]
     */
    protected array $taxIds;

    protected string $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, array $taxIds = [])
    {
        parent::__construct();

        $this->taxIds = $taxIds;
        $this->operator = $operator;
    }

    public function getName(): string
    {
        return 'cartLineItemTaxation';
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchesOneOfTaxations($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->matchesOneOfTaxations($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'taxIds' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [
                new NotBlank(),
                new Choice([
                    self::OPERATOR_EQ,
                    self::OPERATOR_NEQ,
                ]),
            ],
        ];
    }

    /**
     * @throws UnsupportedOperatorException
     * @throws PayloadKeyNotFoundException
     */
    private function matchesOneOfTaxations(LineItem $lineItem): bool
    {
        $taxId = (string) $lineItem->getPayloadValue('taxId');

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return \in_array($taxId, $this->taxIds, true);

            case self::OPERATOR_NEQ:
                return !\in_array($taxId, $this->taxIds, true);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }
}
