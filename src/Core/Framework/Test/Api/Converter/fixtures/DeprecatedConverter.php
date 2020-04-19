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
                'price',
                'tax',
                'taxId',
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

    protected function getConverterFunctions(): array
    {
        return [
            DeprecatedDefinition::ENTITY_NAME => function (array $payload) {
                if (\array_key_exists('price', $payload)) {
                    $payload['prices'] = [$payload['price']];
                    unset($payload['price']);
                }

                return $payload;
            },
        ];
    }
}
