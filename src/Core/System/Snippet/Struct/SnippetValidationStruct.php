<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @phpstan-type MissingSnippets array<string, array<string, array{
 *      path: string,
 *      availableISO: string,
 *      availableValue: string,
 *      keyPath: string
 * }>>
 * @phpstan-type InvalidPluralization array{
 *      isInvalid: bool,
 *      isFixable: bool
 * }
 */
#[Package('discovery')]
class SnippetValidationStruct extends Struct
{
    /**
     * @param MissingSnippets $missingSnippets
     * @param array<string, InvalidPluralization> $invalidPluralization
     */
    public function __construct(
        public readonly array $missingSnippets,
        public readonly array $invalidPluralization,
    ) {
    }
}
