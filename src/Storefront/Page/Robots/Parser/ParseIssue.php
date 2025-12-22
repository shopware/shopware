<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Parser;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore Simple DTO with no business logic
 */
#[Package('framework')]
class ParseIssue
{
    public function __construct(
        public readonly int $lineNumber,
        public readonly string $lineContent,
        public readonly string $reason,
        public readonly ParseIssueSeverity $severity
    ) {
    }
}
