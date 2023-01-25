<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\TaxProvider\TaxProviderCollection;
use Shopware\Core\System\TaxProvider\TaxProviderEntity;

#[Package('checkout')]
class TaxProviderPersister
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $taxProviderRepository)
    {
    }

    public function updateTaxProviders(Manifest $manifest, string $appId, string $defaultLocale, Context $context): void
    {
        $tax = $manifest->getTax();

        if (!$tax) {
            return;
        }

        $taxProviders = $tax->getTaxProviders();

        if (!$taxProviders) {
            return;
        }

        $upserts = [];

        $existingTaxProviders = $this->getExistingTaxProviders($appId, $context);

        foreach ($taxProviders as $taxProvider) {
            $payload = $taxProvider->toArray($defaultLocale);
            $payload['priority'] = (int) $payload['priority'];
            $payload['identifier'] = \sprintf(
                'app\\%s_%s',
                $manifest->getMetadata()->getName(),
                $taxProvider->getIdentifier()
            );

            /** @var TaxProviderEntity|null $existing */
            $existing = $existingTaxProviders->filterByProperty('identifier', $payload['identifier'])->first();

            if ($existing) {
                $payload['id'] = $existing->getId();
            }

            $payload['appId'] = $appId;
            $payload['processUrl'] = $taxProvider->getProcessUrl();

            $upserts[] = $payload;
        }

        $this->taxProviderRepository->upsert($upserts, $context);
    }

    private function getExistingTaxProviders(string $appId, Context $context): TaxProviderCollection
    {
        $criteria = new Criteria();

        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('appId', $appId),
        ]));

        return $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($criteria) {
            /** @var TaxProviderCollection $taxProviders */
            $taxProviders = $this->taxProviderRepository->search($criteria, $context)->getEntities();

            return $taxProviders;
        });
    }
}
