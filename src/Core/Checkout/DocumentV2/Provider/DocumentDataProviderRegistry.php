<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentDataProviderRegistry
{
    /**
     * @var array<string, array<string, AbstractDocumentDataProvider>>
     */
    private array $providersByDocumentType;

    /**
     * @param iterable<AbstractDocumentDataProvider> $documentDataProviders
     */
    public function __construct(iterable $documentDataProviders)
    {
        $providersByDocumentType = [];

        foreach ($documentDataProviders as $provider) {
            $key = $provider->getKey();

            foreach ($provider->getDocumentTypes() as $documentType) {
                if (isset($providersByDocumentType[$documentType][$key])) {
                    throw DocumentV2Exception::duplicateProviderKey($key, $documentType);
                }

                $providersByDocumentType[$documentType][$key] = $provider;
            }
        }

        $this->providersByDocumentType = $providersByDocumentType;
    }

    /**
     * Returns all providers that should contribute render data for the given document type.
     *
     * @return list<AbstractDocumentDataProvider>
     */
    public function getByDocumentType(string $documentType): array
    {
        return array_values($this->providersByDocumentType[$documentType] ?? []);
    }
}
