<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\TaxProvider\TaxProviderCollection;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class TaxProviderPersister implements PersisterInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<TaxProviderCollection> $taxProviderRepository
     */
    public function __construct(private readonly EntityRepository $taxProviderRepository)
    {
    }

    public function persist(AppLifecycleContext $context): void
    {
        if (!$context->hasAppSecret()) {
            return;
        }

        $tax = $context->manifest->getTax();

        if (!$tax) {
            return;
        }

        $taxProviders = $tax->getTaxProviders();

        if (!$taxProviders) {
            return;
        }

        $upserts = [];
        $appId = $context->app->getId();

        $existingTaxProviders = $this->getExistingTaxProviders($appId, $context->context);

        foreach ($taxProviders as $taxProvider) {
            $payload = $taxProvider->toArray($context->defaultLocale);
            $payload['priority'] = (int) $payload['priority'];
            $payload['identifier'] = \sprintf(
                'app\\%s_%s',
                $context->manifest->getMetadata()->getName(),
                $taxProvider->getIdentifier()
            );

            $existing = $existingTaxProviders->filterByProperty('identifier', $payload['identifier'])->first();

            if ($existing) {
                $payload['id'] = $existing->getId();
            }

            $payload['appId'] = $appId;
            $payload['processUrl'] = $taxProvider->getProcessUrl();

            $upserts[] = $payload;
        }

        $this->taxProviderRepository->upsert($upserts, $context->context);
    }

    private function getExistingTaxProviders(string $appId, Context $context): TaxProviderCollection
    {
        $criteria = new Criteria();

        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('appId', $appId),
        ]));

        return $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($criteria) {
            return $this->taxProviderRepository->search($criteria, $context)->getEntities();
        });
    }
}
