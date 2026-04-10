<?php
declare(strict_types=1);

namespace Shopware\Storefront\Framework\Media\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed, use {@see \Shopware\Storefront\Framework\StorefrontFrameworkException::mediaValidatorMissing} instead
 */
#[Package('discovery')]
class MediaValidatorMissingException extends ShopwareHttpException
{
    public function __construct(string $type)
    {
        parent::__construct('No validator for {{ type }} was found.', ['type' => $type]);
    }

    public function getErrorCode(): string
    {
        return 'STOREFRONT__MEDIA_VALIDATOR_MISSING';
    }
}
