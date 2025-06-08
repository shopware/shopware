<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed, use CartException::invalidPriceFieldTypeException() instead
 */
#[Package('framework')]
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
