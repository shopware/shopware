<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OpenApi;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore Simple DTO with no logic.
 */
#[Package('framework')]
final readonly class OpenApiDtoGeneratedFile
{
    public function __construct(
        public string $path,
        public string $contents,
    ) {
    }
}
