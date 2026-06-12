<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Mcp\Xml;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface McpCapabilityItem
{
    public function getName(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $defaultLocale): array;
}
