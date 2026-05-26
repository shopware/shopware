<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AgenticDiscovery\Manifest;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * A single section rendered into an agentic discovery document.
 * `title` is rendered as a Markdown heading (H2 for /agents.md, plain text
 * for /llms.txt). `body` is plain Markdown and inlined verbatim — providers
 * are responsible for escaping untrusted input.
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 */
#[Package('framework')]
class DiscoverySection extends Struct
{
    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly int $priority = 0,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getApiAlias(): string
    {
        return 'agentic_discovery_section';
    }
}
