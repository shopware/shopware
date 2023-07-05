<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use MediaException::duplicatedMediaFileName instead
 */
#[Package('content')]
class DuplicatedMediaFileNameException extends MediaException
{
    public function __construct(
        string $fileName,
        string $fileExtension
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use MediaException::duplicatedMediaFileName instead')
        );

        parent::__construct(
            Response::HTTP_CONFLICT,
            self::MEDIA_DUPLICATED_FILE_NAME,
            'A file with the name "{{ fileName }}.{{ fileExtension }}" already exists.',
            ['fileName' => $fileName, 'fileExtension' => $fileExtension]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'use MediaException::duplicatedMediaFileName instead')
        );

        return 'CONTENT__MEDIA_DUPLICATED_FILE_NAME';
    }
}
