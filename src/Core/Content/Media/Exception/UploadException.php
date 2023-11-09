<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use MediaException instead
 */
#[Package('buyers-experience')]
class UploadException extends MediaException
{
    public function __construct(string $message = '')
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'CONTENT__MEDIA_UPLOAD',
            '{{ message }}',
            ['message' => $message]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );

        return 'CONTENT__MEDIA_UPLOAD';
    }
}
