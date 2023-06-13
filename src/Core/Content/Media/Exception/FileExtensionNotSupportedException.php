<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use MediaException::fileExtensionNotSupported instead
 */
#[Package('content')]
class FileExtensionNotSupportedException extends ShopwareHttpException
{
    public function __construct(
        string $mediaId,
        string $extension
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use MediaException::fileExtensionNotSupported instead')
        );

        parent::__construct(
            'The file extension "{{ extension }}" for media object with id {{ mediaId }} is not supported.',
            ['mediaId' => $mediaId, 'extension' => $extension]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'use MediaException::fileExtensionNotSupported instead')
        );

        return 'CONTENT__MEDIA_FILE_TYPE_NOT_SUPPORTED';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'use MediaException::fileExtensionNotSupported instead')
        );

        return Response::HTTP_BAD_REQUEST;
    }
}
