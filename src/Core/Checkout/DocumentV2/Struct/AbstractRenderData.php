<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentDisplayOptions;
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
 *
 * @see \Shopware\Tests\Integration\Core\Checkout\DocumentV2\Renderer\DocumentRendererSnapshotTest
 */
#[Package('after-sales')]
abstract readonly class AbstractRenderData
{
    /**
     * @param array<string, mixed> $custom
     * @param array<string, mixed> $legacyConfig
     */
    public function __construct(
        public DocumentConfig $config,
        public DocumentCompanyInfo $company,
        public DocumentDisplayOptions $display,
        public string $documentDate,
        public string $documentNumber,
        public ?string $documentComment,
        public array $custom = [],
        /**
         * @deprecated tag:v6.8.0 - will be removed
         */
        public array $legacyConfig = [],
    ) {
    }
}
