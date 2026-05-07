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
     */
    public function __construct(
        public DocumentConfig $config,
        public CompanyInfo $company,
        public array $legacyConfig,
    ) {
    }
}
