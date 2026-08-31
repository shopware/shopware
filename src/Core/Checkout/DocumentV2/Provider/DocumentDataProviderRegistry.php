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
     * @var list<AbstractDocumentDataProvider>
     */
    private array $providers;

    /**
     * @param iterable<AbstractDocumentDataProvider> $documentDataProviders
     */
    public function __construct(iterable $documentDataProviders)
    {
        $this->providers = array_values([...$documentDataProviders]);
    }

    /**
     * Returns all providers that contribute render data for the given document type, each provider
     * deciding via {@see AbstractDocumentDataProvider::supports()}.
     *
     * @throws DocumentV2Exception
     *
     * @return list<AbstractDocumentDataProvider>
     */
    public function getByDocumentType(string $documentType): array
    {
        $matched = [];

        foreach ($this->providers as $provider) {
            if (!$provider->supports($documentType)) {
                continue;
            }

            $key = $provider->getKey();

            if (isset($matched[$key])) {
                throw DocumentV2Exception::duplicateProviderKey($key, $documentType);
            }

            $matched[$key] = $provider;
        }

        return array_values($matched);
    }
}
