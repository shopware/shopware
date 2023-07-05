<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use MediaException::emptyMediaFilename instead
 */
#[Package('content')]
class EmptyMediaFilenameException extends MediaException
{
    public function __construct()
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use MediaException::emptyMediaFilename instead')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_EMPTY_FILE_NAME,
            'A valid filename must be provided.'
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'use MediaException::emptyMediaFilename instead')
        );

        return 'CONTENT__MEDIA_EMPTY_FILE_NAME';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'use MediaException::emptyMediaFilename instead')
        );

        return Response::HTTP_BAD_REQUEST;
    }
}
