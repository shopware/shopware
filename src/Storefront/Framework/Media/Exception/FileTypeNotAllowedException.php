<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Media\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.7.0 - Will be removed, use StorefrontFrameworkException::fileTypeNotAllowed instead
 */
#[Package('buyers-experience')]
class FileTypeNotAllowedException extends ShopwareHttpException
{
    public function __construct(
        string $mimeType,
        string $uploadType
    ) {
        parent::__construct(
            'Type "{{ mimeType }}" of provided file is not allowed for {{ uploadType }}',
            ['mimeType' => $mimeType, 'uploadType' => $uploadType]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'StorefrontFrameworkException'));

        return 'STOREFRONT__MEDIA_ILLEGAL_FILE_TYPE';
    }
}
