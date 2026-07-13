<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OpenApi;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore Simple DTO with no logic.
 */
#[Package('framework')]
final readonly class OpenApiDtoGenerationResult
{
    /**
     * @param list<string> $writtenFiles
     */
    public function __construct(
        public array $writtenFiles,
    ) {
    }
}
