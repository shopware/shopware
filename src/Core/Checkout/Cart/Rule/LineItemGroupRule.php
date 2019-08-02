<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Rule\Container\FilterRule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemGroupRule extends FilterRule
{
    /**
     * @var string
     */
    protected $packagerKey;

    /**
     * @var float
     */
    protected $value;

    /**
     * @var string
     */
    protected $sorterKey;

    /**
     * @var RuleCollection|null
     */
    protected $rules;

    /**
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     * @throws \Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupPackagerNotFoundException
     * @throws \Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupSorterNotFoundException
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $groupDefinition = new LineItemGroupDefinition(
            $this->packagerKey,
            $this->value,
            $this->sorterKey,
            $this->rules
        );

        /** @var LineItemGroupBuilder $builder */
        $builder = $scope->getCart()->getData()->get(LineItemGroupBuilder::class);

        /** @var array $results */
        $results = $builder->findPackages(
            $groupDefinition,
            $scope->getCart(),
            $scope->getSalesChannelContext()
        );

        return count($results) > 0;
    }

    public function getConstraints(): array
    {
        return [
            'packagerKey' => [new NotBlank(), new Type('string')],
            'value' => [new NotBlank(), new Type('numeric')],
            'sorterKey' => [new NotBlank(), new Type('string')],
            'rules' => [new NotBlank(), new Type('container')],
        ];
    }

    public function getName(): string
    {
        return 'cartLineItemInGroup';
    }
}
