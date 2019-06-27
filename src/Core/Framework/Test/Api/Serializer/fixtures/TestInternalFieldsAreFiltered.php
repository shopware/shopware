<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Serializer\fixtures;

use Shopware\Core\Checkout\Test\Cart\Common\FalseRule;
use Shopware\Core\Checkout\Test\Cart\Common\TrueRule;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Rule\Container\AndRule;

class TestInternalFieldsAreFiltered extends SerializationFixture
{
    public function getInput()
    {
        $ruleCollection = new RuleCollection();

        $rule = new RuleEntity();
        $rule->setId('f343a3c119cf42a7841aa0ac5094908c');
        $rule->setName('Test rule');
        $rule->setDescription('Test description');
        $rule->setPayload(new AndRule([new TrueRule(), new FalseRule()]));
        $ruleCollection->add($rule);

        return $ruleCollection;
    }

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
                    ],
                    'meta' => null,
                ],
            ],
            'included' => [],
        ];
    }

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
            ],
        ];
    }
}
