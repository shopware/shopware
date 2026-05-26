<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AgenticDiscovery\Manifest;

use Shopware\Core\Framework\AgenticDiscovery\AgenticDiscoveryDocumentType;
use Shopware\Core\Framework\Log\Package;

/**
 * Stable extension point for contributing custom sections to agentic discovery
 * documents. Implementations are collected via the DI tag
 * `agentic_discovery.section` and called for every document/sales-channel
 * combination. Returning `null` from `getSection()` skips contribution for the
 * current document.
 *
 * Sections appear in the rendered Markdown after the core sections, ordered
 * by descending `getPriority()`. Use priority `> 0` to push a section above
 * the default closing block, `< 0` (or `0`) to place it after the merchant's
 * custom sections.
 *
 * Implementations MUST be deterministic for a given (`type`, `context`)
 * tuple; the result is HTTP-cached.
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 */
#[Package('framework')]
interface DiscoverySectionProvider
{
    public function supports(AgenticDiscoveryDocumentType $type, AgenticDiscoveryContext $context): bool;

    public function getSection(AgenticDiscoveryDocumentType $type, AgenticDiscoveryContext $context): ?DiscoverySection;
}
