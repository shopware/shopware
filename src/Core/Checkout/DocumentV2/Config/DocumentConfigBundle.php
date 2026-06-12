<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Config;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentConfigBundle
{
    /**
     * @param array<string, mixed> $legacyConfig
     *
     * @deprecated tag:v6.8.0 - $legacyConfig will be removed once all fields are migrated to typed properties
     */
    public function __construct(
        public DocumentConfig $config,
        public CompanyInfo $company,
        public array $legacyConfig,
    ) {
    }
}
