<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\TaxProvider;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\TaxProviderExceptions;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\TaxProvider\Payload\TaxProviderPayload;
use Shopware\Core\Framework\App\TaxProvider\Payload\TaxProviderPayloadService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\TaxProvider\TaxProviderCollection;
use Shopware\Core\System\TaxProvider\TaxProviderEntity;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Package('checkout')]
class TaxProviderProcessor
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $taxProviderRepository,
        private readonly LoggerInterface $logger,
        private readonly TaxAdjustment $adjustment,
        private readonly TaxProviderRegistry $registry,
        private readonly TaxProviderPayloadService $payloadService
    ) {
    }

    public function process(Cart $cart, SalesChannelContext $context): void
    {
        if ($context->getTaxState() === CartPrice::TAX_STATE_FREE) {
            return;
        }

        $taxProviders = $this->getTaxProviders($context);

        if ($taxProviders->count() === 0) {
            return;
        }

        $exceptions = new TaxProviderExceptions();

        $result = $this->buildTaxes(
            $taxProviders,
            $cart,
            $context,
            $exceptions
        );

        if (!$result) {
            $this->logger->error($exceptions->getMessage(), ['error' => $exceptions]);

            throw $exceptions;
        }

        $this->adjustment->adjust($cart, $result, $context);
    }

    private function getTaxProviders(SalesChannelContext $context): TaxProviderCollection
    {
        $criteria = (new Criteria())
            ->addAssociations(['availabilityRule', 'app'])
            ->addFilter(
                new AndFilter([
                    new EqualsFilter('active', true),
                    new OrFilter([
                        new EqualsFilter('availabilityRuleId', null),
                        new EqualsAnyFilter('availabilityRuleId', $context->getRuleIds()),
                    ]),
                ])
            );

        /** @var TaxProviderCollection $providers */
        $providers = $this->taxProviderRepository->search($criteria, $context->getContext())->getEntities();

        // we can safely sort the providers in php, as we do not expect more than a couple of providers
        // otherwise we would need to sort them in the database with an index many fields to be performant
        $providers->sortByPriority();

        return $providers;
    }

    private function buildTaxes(
        TaxProviderCollection $providers,
        Cart $cart,
        SalesChannelContext $context,
        TaxProviderExceptions $exceptions,
    ): ?TaxProviderResult {
        /** @var TaxProviderEntity $providerEntity */
        foreach ($providers->getElements() as $providerEntity) {
            // app providers
            if ($providerEntity->getApp() && $providerEntity->getProcessUrl()) {
                return $this->handleAppRequest($providerEntity->getApp(), $providerEntity->getProcessUrl(), $cart, $context);
            }

            $provider = $this->registry->get($providerEntity->getIdentifier());

            if (!$provider) {
                $exceptions->add(
                    $providerEntity->getIdentifier(),
                    new NotFoundHttpException(\sprintf('No tax provider found for identifier %s', $providerEntity->getIdentifier()))
                );

                continue;
            }

            try {
                $taxProviderStruct = $provider->provide($cart, $context);
            } catch (\Throwable $e) {
                $exceptions->add($providerEntity->getIdentifier(), $e);

                continue;
            }

            // taxes given - no need to continue
            if ($taxProviderStruct->declaresTaxes()) {
                return $taxProviderStruct;
            }
        }

        return null;
    }

    private function handleAppRequest(
        AppEntity $app,
        string $processUrl,
        Cart $cart,
        SalesChannelContext $context
    ): ?TaxProviderResult {
        return $this->payloadService->request(
            $processUrl,
            new TaxProviderPayload($cart, $context),
            $app,
            $context->getContext()
        );
    }
}
