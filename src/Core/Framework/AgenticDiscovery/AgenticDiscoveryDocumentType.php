<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AgenticDiscovery;

use Shopware\Core\Framework\Log\Package;

/**
 * Identifies the public-facing discovery document a request is producing.
 * Used by `DiscoverySectionProvider` implementations to deliver per-document
 * content (e.g. `/agents.md` gets the agent operating manual, `/llms.txt`
 * gets the short curator's note).
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 */
#[Package('framework')]
enum AgenticDiscoveryDocumentType: string
{
    case AGENTS_MD = 'agents.md';
    case LLMS_TXT = 'llms.txt';
    case LLMS_FULL_TXT = 'llms-full.txt';
    case AGENTIC_SITEMAP = 'sitemap_agentic_discovery.xml';
}
