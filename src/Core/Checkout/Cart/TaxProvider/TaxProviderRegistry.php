<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\TaxProvider;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class TaxProviderRegistry
{
    /**
     * @var array<AbstractTaxProvider> key is providerIdentifier
     */
    private array $providers = [];

    /**
     * @internal
     *
     * @param array<AbstractTaxProvider> $providers
     */
    public function __construct(iterable $providers)
    {
        /** @var AbstractTaxProvider $provider */
        foreach ($providers as $provider) {
            $identifier = $provider::class;

            if (!$this->has($identifier)) {
                $this->providers[$identifier] = $provider;
            }
        }
    }

    public function has(string $identifier): bool
    {
        return \array_key_exists($identifier, $this->providers);
    }

    public function get(string $identifier): ?AbstractTaxProvider
    {
        if (!$this->has($identifier)) {
            return null;
        }

        return $this->providers[$identifier];
    }
}
