<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @major-deprecated (flag:FEATURE_NEXT_17016) This rule will be removed. Use the LineItemRule instead.
 */
class LineItemsInCartRule extends Rule
{
    /**
     * @var string[]|null
     */
    protected ?array $identifiers;

    protected string $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, ?array $identifiers = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->identifiers = $identifiers;
    }

    public function match(RuleScope $scope): bool
    {
        if (Feature::isActive('FEATURE_NEXT_17016')) {
            throw new \RuntimeException('LineItemsInCartRule is deprecated and will be removed. Use LineItemRule instead.');
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        if ($this->identifiers === null) {
            return false;
        }

        $elements = $scope->getCart()->getLineItems()->getFlat();
        $identifiers = array_map(static function (LineItem $element) {
            return $element->getReferencedId() ?: null;
        }, $elements);

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return !empty(array_intersect($identifiers, $this->identifiers));

            case self::OPERATOR_NEQ:
                return empty(array_intersect($identifiers, $this->identifiers));

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        return [
            'identifiers' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [new NotBlank(), new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }

    public function getName(): string
    {
        return 'cartLineItemsInCart';
    }
}
