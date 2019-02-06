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
                'productPriceRules' => [
                    'data' => [],
                    'links' => ['related' => '/api/rule/f343a3c119cf42a7841aa0ac5094908c/product-price-rules'],
                ],
                'shippingMethods' => [
                    'data' => [],
                    'links' => ['related' => '/api/rule/f343a3c119cf42a7841aa0ac5094908c/shipping-methods'],
                ],
            ],
            'meta' => [
                'viewData' => [
                    'name' => null,
                    'description' => null,
                    'priority' => null,
                    'payload' => null,
                    'createdAt' => null,
                    'updatedAt' => null,
                    'moduleTypes' => null,
                    'discountSurcharges' => null,
                    'productPriceRules' => null,
                    'conditions' => null,
                    'invalid' => null,
                    '_uniqueIdentifier' => null,
                    'viewData' => null,
                    'extensions' => [],
                    'id' => null,
                    'versionId' => null,
                    'attributes' => null,
                    'shippingMethods' => null,
                    '_class' => 'Shopware\Core\Content\Rule\RuleEntity',
                ],
            ],
        ],
    ],
    'included' => [],
];
