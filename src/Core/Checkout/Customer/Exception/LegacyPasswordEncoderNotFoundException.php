<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class LegacyPasswordEncoderNotFoundException extends ShopwareHttpException
{
    public function __construct(string $encoder)
    {
        parent::__construct(
            'Encoder with name "{{ encoder }}" not found.',
            ['encoder' => $encoder]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__LEGACY_PASSWORD_ENCODER_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
