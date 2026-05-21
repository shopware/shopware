<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Checkout\DocumentV2\Config\CompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * Base class for provider-specific DTOs stored in RenderInput.
 *
 * Each document data provider returns its own AbstractRenderData subtype so renderers can consume
 * typed, precomputed input instead of reaching back into the data loading layer.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
abstract readonly class AbstractRenderData
{
    /**
     * @param array<string, mixed> $legacyConfig
     */
    public function __construct(
        public DocumentConfig $config,
        public CompanyInfo $company,
        public string $documentDate,
        public string $documentNumber,
        public ?string $documentComment,
        /**
         * @deprecated tag:v6.8.0 - will be removed
         */
        public array $legacyConfig = [],
    ) {
    }
}
