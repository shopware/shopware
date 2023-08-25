<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use MediaException::couldNotRenameFile instead
 */
#[Package('content')]
class CouldNotRenameFileException extends MediaException
{
    public function __construct(
        string $mediaId,
        string $oldFileName
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use MediaException::couldNotRenameFile instead')
        );

        parent::__construct(
            Response::HTTP_CONFLICT,
            self::MEDIA_COULD_NOT_RENAME_FILE,
            'Could not rename file for media with id: {{ mediaId }}. Rollback to filename: "{{ oldFileName }}"',
            ['mediaId' => $mediaId, 'oldFileName' => $oldFileName]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'use MediaException::couldNotRenameFile instead')
        );

        return 'CONTENT__MEDIA_COULD_NOT_RENAME_FILE';
    }
}
