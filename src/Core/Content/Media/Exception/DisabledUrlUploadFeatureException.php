<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('content')]
class DisabledUrlUploadFeatureException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct(
            'The feature to upload a media via URL is disabled.'
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_URL_UPLOAD_DISABLED';
    }
}
