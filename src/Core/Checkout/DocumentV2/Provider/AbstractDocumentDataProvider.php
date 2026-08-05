<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider;

use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\ProviderInput;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * Collects and enriches document-type specific input data before rendering starts.
 *
 * Each matching provider is called once per generation request. Its output is stored in the
 * RenderInput so multiple renderers can reuse the same prepared data.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
abstract readonly class AbstractDocumentDataProvider
{
    /**
     * Unique key under which the provider result is stored in RenderInput.
     */
    abstract public function getKey(): string;

    /**
     * Whether this provider contributes data for the given document type.
     */
    abstract public function supports(string $documentType): bool;

    /**
     * Allows a provider to preload additional order associations before data extraction.
     */
    public function enrichOrderCriteria(Criteria $criteria): void
    {
    }

    /**
     * Builds the provider-specific rendering data for the given input.
     */
    abstract public function provideRenderingData(
        ProviderInput $input,
        Context $context,
    ): AbstractRenderData;
}
