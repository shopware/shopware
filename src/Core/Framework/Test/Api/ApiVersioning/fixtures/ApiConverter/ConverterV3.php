<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\ApiConverter;

use Shopware\Core\Framework\Api\Converter\ApiConverter;

class ConverterV3 extends ApiConverter
{
    public function getApiVersion(): int
    {
        return 3;
    }

    protected function getDeprecations(): array
    {
        return [
            '_test_bundle' => [
                'description',
            ],
        ];
    }

    protected function getNewFields(): array
    {
        return [
            '_test_bundle' => [
                'translatedDescription',
                'pseudoPrice',
                'translations',
                'prices',
            ],
            '_test_bundle_translation' => true,
            '_test_bundle_price' => true,
        ];
    }

    /**
     * @return callable[]
     */
    protected function getConverterFunctions(): array
    {
        return [
            '_test_bundle' => function (array $payload): array {
                if (array_key_exists('description', $payload)) {
                    $payload['translatedDescription'] = $payload['description'];

                    unset($payload['description']);
                }

                return $payload;
            },
        ];
    }
}
