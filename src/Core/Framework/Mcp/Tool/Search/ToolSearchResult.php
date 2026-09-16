<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool\Search;

use Mcp\Schema\Tool;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[Package('framework')]
final readonly class ToolSearchResult
{
    /**
     * @param list<string> $matchedIn
     */
    public function __construct(
        public Tool $tool,
        public float $score,
        public array $matchedIn,
    ) {
    }
}
