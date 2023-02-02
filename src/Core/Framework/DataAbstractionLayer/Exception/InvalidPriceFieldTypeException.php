<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class InvalidPriceFieldTypeException extends ShopwareHttpException
{
    public function __construct(string $type)
    {
        parent::__construct(
            'The price field does not contain a valid "type" value. Received {{ type }} ',
            ['type' => $type]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_PRICE_FIELD_TYPE';
    }
}
