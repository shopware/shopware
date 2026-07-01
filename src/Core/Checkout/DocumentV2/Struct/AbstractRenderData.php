<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentDisplayOptions;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Core\Checkout\DocumentV2\Renderer\DocumentRendererSnapshotTest;

/**
 * Base class for provider-specific DTOs stored in RenderInput.
 *
 * Each document data provider returns its own AbstractRenderData subtype so renderers can consume
 * typed, precomputed input instead of reaching back into the data loading layer.
 *
 * Carries the (format -> template path) mapping used by every renderer. Subclasses pass their
 * mapping into the parent constructor; renderers call {@see self::templatePathFor()} to resolve
 * the right template without hard-coding paths or knowing about the provider.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see DocumentRendererSnapshotTest
 */
#[Package('after-sales')]
abstract readonly class AbstractRenderData
{
    /**
     * @param array<string, string> $templatePaths
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
        public array $templatePaths,
        public array $custom = [],
        /**
         * @deprecated tag:v6.8.0 - will be removed
         */
        public array $legacyConfig = [],
    ) {
    }

    public function templatePathFor(string $format): string
    {
        if (!isset($this->templatePaths[$format])) {
            throw DocumentV2Exception::templatePathNotFound($format);
        }

        return $this->templatePaths[$format];
    }
}
