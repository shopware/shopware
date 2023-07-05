<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use MediaException::illegalUrl instead
 */
#[Package('content')]
class IllegalUrlException extends MediaException
{
    public function __construct(string $url)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use MediaException::illegalUrl instead')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_ILLEGAL_URL,
            'Provided URL "{{ url }}" is not allowed.',
            ['url' => $url]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'use MediaException::illegalUrl instead')
        );

        return 'CONTENT__MEDIA_ILLEGAL_URL';
    }
}
