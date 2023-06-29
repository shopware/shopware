<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use MediaException::mediaNotFound instead
 */
#[Package('content')]
class MediaNotFoundException extends ShopwareHttpException
{
    public function __construct(string $mediaId)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use MediaException::mediaNotFound instead')
        );

        parent::__construct(
            'Media for id {{ mediaId }} not found.',
            ['mediaId' => $mediaId]
        );
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'use MediaException::mediaNotFound instead')
        );

        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'use MediaException::mediaNotFound instead')
        );

        return 'CONTENT__MEDIA_NOT_FOUND';
    }
}
