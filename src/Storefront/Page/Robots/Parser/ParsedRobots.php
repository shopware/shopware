<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Parser;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirective;
use Shopware\Storefront\Page\Robots\Struct\RobotsUserAgentBlock;

#[Package('framework')]
class ParsedRobots
{
    /**
     * @param list<RobotsUserAgentBlock> $userAgentBlocks
     * @param list<RobotsDirective> $orphanedPathDirectives
     * @param list<ParseIssue> $issues
     */
    public function __construct(
        public readonly array $userAgentBlocks,
        public readonly array $orphanedPathDirectives,
        public readonly array $issues = []
    ) {
    }

    public function hasUserAgentBlocks(): bool
    {
        return \count($this->userAgentBlocks) > 0;
    }

    public function hasErrors(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->severity === ParseIssueSeverity::ERROR) {
                return true;
            }
        }

        return false;
    }

    public function hasWarnings(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->severity === ParseIssueSeverity::WARNING) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<ParseIssue>
     */
    public function getErrors(): array
    {
        return array_values(array_filter($this->issues, static fn (ParseIssue $issue) => $issue->severity === ParseIssueSeverity::ERROR));
    }

    /**
     * @return list<ParseIssue>
     */
    public function getWarnings(): array
    {
        return array_values(array_filter($this->issues, static fn (ParseIssue $issue) => $issue->severity === ParseIssueSeverity::WARNING));
    }
}
