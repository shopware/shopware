<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentDataProviderRegistry
{
    /**
     * @param iterable<AbstractDocumentDataProvider> $documentDataProviders
     */
    public function __construct(
        private iterable $documentDataProviders
    ) {
    }

    /**
     * Returns all providers that should contribute render data for the given document type.
     *
     * @return list<AbstractDocumentDataProvider>
     */
    public function getByDocumentType(string $documentType): array
    {
        $providers = [];

        foreach ($this->documentDataProviders as $provider) {
            if (\in_array($documentType, $provider->getDocumentTypes(), true)) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }
}
