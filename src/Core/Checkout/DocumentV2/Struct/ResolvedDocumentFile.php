<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class ResolvedDocumentFile
{
    public const SOURCE_LEGACY = 'legacy';

    public const SOURCE_V2 = 'v2';

    public function __construct(
        public MediaEntity $media,
        public string $format,
        public string $fileExtension,
        public string $mimeType,
        public string $fileName,
        public string $source,
    ) {
    }
}
