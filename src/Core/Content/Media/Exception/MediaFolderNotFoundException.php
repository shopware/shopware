<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use MediaException::mediaFolderNotFound instead
 */
#[Package('content')]
class MediaFolderNotFoundException extends MediaException
{
    public function __construct(string $folderId)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use MediaException::mediaFolderNotFound instead')
        );

        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::MEDIA_FOLDER_NOT_FOUND,
            'Could not find media folder with id: "{{ folderId }}"',
            ['folderId' => $folderId]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'use MediaException::mediaFolderNotFound instead')
        );

        return 'CONTENT__MEDIA_FOLDER_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'use MediaException::mediaFolderNotFound instead')
        );

        return Response::HTTP_NOT_FOUND;
    }
}
