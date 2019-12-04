<?php
declare(strict_types=1);

namespace Shopware\Storefront\Framework\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

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
