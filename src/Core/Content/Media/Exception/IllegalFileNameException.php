<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.6.0 - will be removed, use MediaException::illegalFileName instead
 */
#[Package('content')]
class IllegalFileNameException extends ShopwareHttpException
{
    public function __construct(
        string $filename,
        string $cause
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use MediaException::illegalFileName instead')
        );

        parent::__construct(
            'Provided filename "{{ fileName }}" is not permitted: {{ cause }}',
            ['fileName' => $filename, 'cause' => $cause]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'use MediaException::illegalFileName instead')
        );

        return 'CONTENT__MEDIA_ILLEGAL_FILE_NAME';
    }
}
