<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class CanNotLoadMetadataException extends ShopwareHttpException
{
    public function __construct(string $message, array $parameters = [])
    {
        parent::__construct($message, $parameters);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_CAN_NOT_LOAD_METADATA';
    }
}
