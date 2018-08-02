<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class UploadException extends ShopwareHttpException
{
    protected $code = 'SHOPWARE_UPLOAD_EXCEPTION';

    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}
