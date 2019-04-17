<?php declare(strict_types=1);

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
                'attributes' => null,
                'createdAt' => null,
                'updatedAt' => null,
                'moduleTypes' => null,
            ],
            'links' => ['self' => '/api/rule/f343a3c119cf42a7841aa0ac5094908c'],
            'relationships' => [
                'conditions' => [
                    'data' => [],
                    'links' => ['related' => '/api/rule/f343a3c119cf42a7841aa0ac5094908c/conditions'],
                ],
                'discountSurcharges' => [
                    'data' => [],
                    'links' => ['related' => '/api/rule/f343a3c119cf42a7841aa0ac5094908c/discount-surcharges'],
                ],
                'productPrices' => [
                    'data' => [],
                    'links' => ['related' => '/api/rule/f343a3c119cf42a7841aa0ac5094908c/product-prices'],
                ],
                'shippingMethods' => [
                    'data' => [],
                    'links' => ['related' => '/api/rule/f343a3c119cf42a7841aa0ac5094908c/shipping-methods'],
                ],
                'shippingMethodPrices' => [
                    'data' => [],
                    'links' => ['related' => '/api/rule/f343a3c119cf42a7841aa0ac5094908c/shipping-method-prices'],
                ],
                'paymentMethods' => [
                    'data' => [],
                    'links' => ['related' => '/api/rule/f343a3c119cf42a7841aa0ac5094908c/payment-methods'],
                ],
                'personaPromotions' => [
                    'data' => [],
                    'links' => ['related' => '/api/rule/f343a3c119cf42a7841aa0ac5094908c/persona-promotions'],
                ],
                'scopePromotions' => [
                    'data' => [],
                    'links' => ['related' => '/api/rule/f343a3c119cf42a7841aa0ac5094908c/scope-promotions'],
                ],
                'discountPromotions' => [
                    'data' => [],
                    'links' => ['related' => '/api/rule/f343a3c119cf42a7841aa0ac5094908c/discount-promotions'],
                ],
                'shippingMethodPriceCalculations' => [
                    'data' => [],
                    'links' => ['related' => '/api/rule/f343a3c119cf42a7841aa0ac5094908c/shipping-method-price-calculations'],
                ],
                'orderPromotions' => [
                    'data' => [],
                    'links' => ['related' => '/api/rule/f343a3c119cf42a7841aa0ac5094908c/order-promotions'],
                ],
            ],
            'meta' => null,
        ],
    ],
    'included' => [],
];
