<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.6.0 - will be removed, use MediaException::disableUrlUploadFeature instead
 */
#[Package('content')]
class DisabledUrlUploadFeatureException extends ShopwareHttpException
{
    public function __construct()
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use MediaException::disableUrlUploadFeature instead')
        );

        parent::__construct(
            'The feature to upload a media via URL is disabled.'
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'use MediaException::disableUrlUploadFeature instead')
        );

        return 'CONTENT__MEDIA_URL_UPLOAD_DISABLED';
    }
}
