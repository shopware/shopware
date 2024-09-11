<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - Will be removed. Use OrderException::deliveryWithoutAddress instead
 */
#[Package('checkout')]
class DeliveryWithoutAddressException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Delivery contains no shipping address');
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'OrderException::deliveryWithoutAddress')
        );

        return 'CHECKOUT__DELIVERY_WITHOUT_ADDRESS';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'OrderException::deliveryWithoutAddress')
        );

        return Response::HTTP_BAD_REQUEST;
    }
}
