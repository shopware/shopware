<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - Will be removed. Use OrderException::wrongGuestCredentials instead
 */
#[Package('checkout')]
class WrongGuestCredentialsException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Wrong credentials for guest authentication.');
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'OrderException::wrongGuestCredentials')
        );

        return 'CHECKOUT__GUEST_WRONG_CREDENTIALS';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'OrderException::wrongGuestCredentials')
        );

        return Response::HTTP_FORBIDDEN;
    }
}
