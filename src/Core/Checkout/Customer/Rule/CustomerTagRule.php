<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\Tag\TagCollection;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class CustomerTagRule extends Rule
{
    /**
     * @var string
     */
    protected $operator;

    /**
     * @var string[]
     */
    protected $identifiers;

    public function __construct(string $operator = self::OPERATOR_EQ, ?array $identifiers = null)
    {
        parent::__construct();
        $this->operator = $operator;
        $this->identifiers = $identifiers;
    }

    public function getName(): string
    {
        return 'customerTag';
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $customer = $scope->getSalesChannelContext()->getCustomer();

        if (!$customer) {
            return false;
        }

        $tagIds = $this->extractTagIds($customer);

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return !empty(array_intersect($tagIds, $this->identifiers));

            case self::OPERATOR_NEQ:
                return empty(array_intersect($tagIds, $this->identifiers));

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        return [
            'identifiers' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [new NotBlank(), new Choice([Rule::OPERATOR_EQ, Rule::OPERATOR_NEQ])],
        ];
    }

    private function extractTagIds(CustomerEntity $customer): array
    {
        /** @var TagCollection $tagCollection */
        $tagCollection = $customer->getTags();

        if (!$tagCollection) {
            return [];
        }

        return $tagCollection->map(function ($tag) {
            return $tag->getId();
        });
    }
}
