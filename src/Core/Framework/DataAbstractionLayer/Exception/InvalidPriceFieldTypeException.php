<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed, use CartException::invalidPriceFieldTypeException() instead
 */
#[Package('framework')]
class InvalidPriceFieldTypeException extends CartException
{
    public function __construct(string $type)
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $this->getErrorCode(),
            'The price field does not contain a valid "type" value. Received {{ type }} ',
            ['type' => $type]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Will be removed, use CartException::invalidPriceFieldTypeException instead');

        return 'FRAMEWORK__INVALID_PRICE_FIELD_TYPE';
    }
}
