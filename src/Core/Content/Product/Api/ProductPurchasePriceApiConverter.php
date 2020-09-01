<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Api;

use Shopware\Core\Framework\Api\Converter\ApiConverter;

/**
 * @deprecated tag:v6.4.0 - Will be removed in 6.4.0
 */
class ProductPurchasePriceApiConverter extends ApiConverter
{
    /**
     * {@inheritdoc}
     */
    public function getApiVersion(): int
    {
        return 3;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDeprecations(): array
    {
        return [
            'product' => [
                'purchasePrice',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getNewFields(): array
    {
        return [
            'product' => [
                'purchasePrices',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getConverterFunctions(): array
    {
        // No need to convert any values here, because the database trigger keep both fields in sync. See also
        // Shopware\Core\Migration\Migration1582724349294AddNetAndGrossPurchasePrice
        return [];
    }
}
