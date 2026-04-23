<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * Represents one rendered format output before it is persisted to media storage.
 *
 * It contains both the binary or textual content and the metadata needed by the renderer to
 * create the final persisted file artifact.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class RenderResult
{
    public function __construct(
        public string $format,
        public string $content,
        public string $fileName,
        public string $fileExtension,
        public string $mimeType,
    ) {
    }
}
