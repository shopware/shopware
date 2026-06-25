<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Rendering;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore Simple value object without behavior.
 */
#[Package('framework')]
final readonly class SalesChannelFileRenderResult
{
    public function __construct(
        public string $fileName,
        public string $content,
        public string $contentType,
    ) {
    }
}
