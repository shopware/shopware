<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\ApiConverter;

use Shopware\Core\Framework\Api\Converter\ApiConverter;

class ConverterV2 extends ApiConverter
{
    public function getApiVersion(): int
    {
        return 2;
    }

    protected function getDeprecations(): array
    {
        return [
            '_test_bundle' => [
                'discountType',
                'longDescription',
            ],
        ];
    }

    protected function getNewFields(): array
    {
        return [
            '_test_bundle' => [
                'isAbsolute',
            ],
        ];
    }

    /**
     * @return callable[]
     */
    protected function getConverterFunctions(): array
    {
        return [
            '_test_bundle' => function (array $payload): array {
                if (\array_key_exists('discountType', $payload)) {
                    $payload['isAbsolute'] = $payload['discountType'] === 'absolute';

                    unset($payload['discountType']);
                }

                return $payload;
            },
        ];
    }
}
