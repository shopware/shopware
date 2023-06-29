<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.6.0 - will be removed, use MediaException::illegalUrl instead
 */
#[Package('content')]
class IllegalUrlException extends ShopwareHttpException
{
    public function __construct(string $url)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use MediaException::illegalUrl instead')
        );

        parent::__construct(
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
