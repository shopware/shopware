<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - reason:remove-exception - will be removed, use CustomerException::legacyPasswordEncoderNotFound instead
 */
#[Package('customer-order')]
class LegacyPasswordEncoderNotFoundException extends CustomerException
{
    public function __construct(string $encoder)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::LEGACY_PASSWORD_ENCODER_NOT_FOUND,
            'Encoder with name "{{ encoder }}" not found.',
            ['encoder' => $encoder]
        );
    }
}
