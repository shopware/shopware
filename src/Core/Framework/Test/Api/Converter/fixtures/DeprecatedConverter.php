<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Converter\fixtures;

use Shopware\Core\Framework\Api\Converter\ApiConverter;

class DeprecatedConverter extends ApiConverter
{
    public function getApiVersion(): int
    {
        return 2;
    }

    protected function getDeprecations(): array
    {
        return [
            DeprecatedDefinition::ENTITY_NAME => [
                'price' => function (array $payload) {
                    $payload['prices'] = [$payload['price']];
                    unset($payload['price']);

                    return $payload;
                },
                'tax' => true,
                'taxId' => true,
            ],
            DeprecatedEntityDefinition::ENTITY_NAME => true,
        ];
    }

    protected function getNewFields(): array
    {
        return [
            DeprecatedDefinition::ENTITY_NAME => [
                'prices',
                'product',
                'productId',
            ],
            NewEntityDefinition::ENTITY_NAME => true,
        ];
    }
}
