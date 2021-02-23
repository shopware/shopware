<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupPackagerNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupSorterNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Rule\Container\FilterRule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemGroupRule extends FilterRule
{
    protected string $groupId;

    protected string $packagerKey;

    protected float $value;

    protected string $sorterKey;

    protected ?RuleCollection $rules;

    /**
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     * @throws LineItemGroupPackagerNotFoundException
     * @throws LineItemGroupSorterNotFoundException
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $groupDefinition = new LineItemGroupDefinition(
            $this->groupId,
            $this->packagerKey,
            $this->value,
            $this->sorterKey,
            $this->rules ?? new RuleCollection()
        );

        /** @var LineItemGroupBuilder|null $builder */
        $builder = $scope->getCart()->getData()->get(LineItemGroupBuilder::class);

        if (!$builder instanceof LineItemGroupBuilder) {
            return false;
        }

        $results = $builder->findGroupPackages(
            [$groupDefinition],
            $scope->getCart(),
            $scope->getSalesChannelContext()
        );

        return $results->hasFoundItems();
    }

    public function getConstraints(): array
    {
        return [
            'groupId' => [new NotBlank(), new Type('string')],
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
