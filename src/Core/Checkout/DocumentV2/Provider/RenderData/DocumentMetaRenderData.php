<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider\RenderData;

use Shopware\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentDisplayOptions;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Framework\Log\Package;

/**
 * Cross-cutting render data shared by every document type: the resolved configuration, company
 * info, display options and document identity. Provided once per generation run by
 * {@see \Shopware\Core\Checkout\DocumentV2\Provider\DocumentMetaProvider}
 *
 * @experimental stableVersion:v6.8.0 feature:DOCUMENT_GENERATION_REWORK
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class DocumentMetaRenderData extends AbstractRenderData
{
    /**
     * @internal
     *
     * @param array<string, mixed> $legacyConfig
     */
    public function __construct(
        public DocumentConfig $config,
        public DocumentCompanyInfo $company,
        public DocumentDisplayOptions $display,
        public string $documentDate,
        public string $documentNumber,
        public ?string $documentComment,
        /**
         * @deprecated tag:v6.8.0 - compat fallback for the flat `config.*` template contract
         */
        public array $legacyConfig = [],
    ) {
    }
}
