<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use MediaException::disableUrlUploadFeature instead
 */
#[Package('content')]
class DisabledUrlUploadFeatureException extends MediaException
{
    public function __construct()
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use MediaException::disableUrlUploadFeature instead')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_DISABLE_URL_UPLOAD_FEATURE,
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
