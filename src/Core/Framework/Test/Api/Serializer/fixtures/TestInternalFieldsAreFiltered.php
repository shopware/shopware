<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Serializer\fixtures;

use Shopware\Core\Checkout\Test\Cart\Common\FalseRule;
use Shopware\Core\Checkout\Test\Cart\Common\TrueRule;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Rule\Container\AndRule;

/**
 * @internal
 */
class TestInternalFieldsAreFiltered extends SerializationFixture
{
    /**
     * @return RuleCollection|RuleEntity
     */
    public function getInput(): EntityCollection|Entity
    {
        $ruleCollection = new RuleCollection();

        $rule = new RuleEntity();
        $rule->setId('f343a3c119cf42a7841aa0ac5094908c');
        $rule->setName('Test rule');
        $rule->setDescription('Test description');
        $rule->setPayload(new AndRule([new TrueRule(), new FalseRule()]));
        $rule->internalSetEntityData('rule', new FieldVisibility([]));
        $ruleCollection->add($rule);

        return $ruleCollection;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJsonApiFixtures(string $baseUrl): array
    {
        return [
            'data' => [
                [
                    'id' => 'f343a3c119cf42a7841aa0ac5094908c',
                    'type' => 'rule',
                    'attributes' => [
                        'name' => 'Test rule',
                        'priority' => null,
                        'description' => 'Test description',
                        'invalid' => null,
                        'customFields' => null,
                        'createdAt' => null,
                        'updatedAt' => null,
                        'moduleTypes' => null,
                    ],
                    'links' => ['self' => sprintf('%s/rule/f343a3c119cf42a7841aa0ac5094908c', $baseUrl)],
                    'relationships' => [
                        'conditions' => [
                            'data' => [],
                            'links' => ['related' => sprintf('%s/rule/f343a3c119cf42a7841aa0ac5094908c/conditions', $baseUrl)],
                        ],
                        'productPrices' => [
                            'data' => [],
                            'links' => ['related' => sprintf('%s/rule/f343a3c119cf42a7841aa0ac5094908c/product-prices', $baseUrl)],
                        ],
                        'shippingMethods' => [
                            'data' => [],
                            'links' => ['related' => sprintf('%s/rule/f343a3c119cf42a7841aa0ac5094908c/shipping-methods', $baseUrl)],
                        ],
                        'shippingMethodPrices' => [
                            'data' => [],
                            'links' => ['related' => sprintf('%s/rule/f343a3c119cf42a7841aa0ac5094908c/shipping-method-prices', $baseUrl)],
                        ],
                        'paymentMethods' => [
                            'data' => [],
                            'links' => ['related' => sprintf('%s/rule/f343a3c119cf42a7841aa0ac5094908c/payment-methods', $baseUrl)],
                        ],
                        'personaPromotions' => [
                            'data' => [],
                            'links' => ['related' => sprintf('%s/rule/f343a3c119cf42a7841aa0ac5094908c/persona-promotions', $baseUrl)],
                        ],
                        'shippingMethodPriceCalculations' => [
                            'data' => [],
                            'links' => ['related' => sprintf('%s/rule/f343a3c119cf42a7841aa0ac5094908c/shipping-method-price-calculations', $baseUrl)],
                        ],
                        'orderPromotions' => [
                            'data' => [],
                            'links' => ['related' => sprintf('%s/rule/f343a3c119cf42a7841aa0ac5094908c/order-promotions', $baseUrl)],
                        ],
                        'cartPromotions' => [
                            'data' => [],
                            'links' => ['related' => sprintf('%s/rule/f343a3c119cf42a7841aa0ac5094908c/cart-promotions', $baseUrl)],
                        ],
                        'promotionDiscounts' => [
                            'data' => [],
                            'links' => ['related' => sprintf('%s/rule/f343a3c119cf42a7841aa0ac5094908c/promotion-discounts', $baseUrl)],
                        ],
                        'promotionSetGroups' => [
                            'data' => [],
                            'links' => ['related' => sprintf('%s/rule/f343a3c119cf42a7841aa0ac5094908c/promotion-set-groups', $baseUrl)],
                        ],
                    ],
                    'meta' => null,
                ],
            ],
            'included' => [],
        ];
    }

    /**
     * @return array<array<string, mixed>>
     */
    protected function getJsonFixtures(): array
    {
        return [
            [
                'id' => 'f343a3c119cf42a7841aa0ac5094908c',
                'name' => 'Test rule',
                'description' => 'Test description',
                'priority' => null,
                'moduleTypes' => null,
                'productPrices' => null,
                'shippingMethods' => null,
                'paymentMethods' => null,
                'conditions' => null,
                'invalid' => null,
                'customFields' => null,
                'shippingMethodPrices' => null,
                'promotionDiscounts' => null,
                'shippingMethodPriceCalculations' => null,
                'personaPromotions' => null,
                'orderPromotions' => null,
                'cartPromotions' => null,
                '_uniqueIdentifier' => 'f343a3c119cf42a7841aa0ac5094908c',
                'versionId' => null,
                'translated' => [],
                'createdAt' => null,
                'updatedAt' => null,
                'extensions' => [],
                'promotionSetGroups' => null,
                'apiAlias' => 'rule',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $fixtures
     *
     * @return array<string, mixed>
     */
    protected function removeProtectedSalesChannelJsonApiData(array $fixtures): array
    {
        unset(
            $fixtures['data'][0]['relationships']['productPrices'],
            $fixtures['data'][0]['relationships']['shippingMethods'],
            $fixtures['data'][0]['relationships']['paymentMethods'],
            $fixtures['data'][0]['relationships']['shippingMethodPrices'],
            $fixtures['data'][0]['relationships']['promotionDiscounts'],
            $fixtures['data'][0]['relationships']['shippingMethodPriceCalculations'],
            $fixtures['data'][0]['relationships']['personaPromotions'],
            $fixtures['data'][0]['relationships']['orderPromotions'],
            $fixtures['data'][0]['relationships']['cartPromotions'],
            $fixtures['data'][0]['relationships']['promotionSetGroups']
        );

        return $fixtures;
    }

    /**
     * @param array<int, mixed> $fixtures
     *
     * @return array<int, mixed>
     */
    protected function removeProtectedSalesChannelJsonData(array $fixtures): array
    {
        unset(
            $fixtures[0]['productPrices'],
            $fixtures[0]['shippingMethods'],
            $fixtures[0]['paymentMethods'],
            $fixtures[0]['shippingMethodPrices'],
            $fixtures[0]['promotionDiscounts'],
            $fixtures[0]['shippingMethodPriceCalculations'],
            $fixtures[0]['personaPromotions'],
            $fixtures[0]['orderPromotions'],
            $fixtures[0]['cartPromotions'],
            $fixtures[0]['promotionSetGroups']
        );

        return $fixtures;
    }
}
