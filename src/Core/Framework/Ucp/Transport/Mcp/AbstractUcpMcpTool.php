<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Mcp;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpVersion;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Convenience base class that builds outputSchema references to the official
 * UCP JSON schemas under ucp.dev.
 */
#[Package('framework')]
abstract class AbstractUcpMcpTool implements UcpMcpToolInterface
{
    public function outputSchema(): ?array
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function ucpSchemaRef(string $schema, string $defs): array
    {
        return [
            '$ref' => 'https://ucp.dev/' . UcpVersion::CURRENT . '/schemas/shopping/' . $schema . '#/$defs/' . $defs,
        ];
    }
}
