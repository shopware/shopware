<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AgenticDiscovery;

use Shopware\Core\Framework\AgenticDiscovery\AgenticDiscoveryDocumentType;
use Shopware\Core\Framework\AgenticDiscovery\Manifest\AgenticManifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 *
 * @internal
 */
#[Package('framework')]
class AgenticDiscoveryPage extends Struct
{
    public function __construct(
        private readonly AgenticDiscoveryDocumentType $type,
        private readonly AgenticManifest $manifest,
    ) {
    }

    public function getType(): AgenticDiscoveryDocumentType
    {
        return $this->type;
    }

    public function getManifest(): AgenticManifest
    {
        return $this->manifest;
    }

    public function getApiAlias(): string
    {
        return 'agentic_discovery_page';
    }
}
