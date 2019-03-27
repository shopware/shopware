<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Storefront;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Exception\InvalidParameterException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Language\LanguageDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StorefrontSalesChannelController extends AbstractController
{
    /**
     * @var EntityRepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $countryRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $countryStateRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $shippingMethodRepository;

    public function __construct(
        EntityRepositoryInterface $currencyRepository,
        EntityRepositoryInterface $languageRepository,
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $countryStateRepository,
        EntityRepositoryInterface $paymentMethodRepository,
        EntityRepositoryInterface $shippingMethodRepository
    ) {
        $this->currencyRepository = $currencyRepository;
        $this->languageRepository = $languageRepository;
        $this->countryRepository = $countryRepository;
        $this->countryStateRepository = $countryStateRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
    }

    /**
     * @Route("/storefront-api/v{version}/currency", name="storefront-api.currency.list", methods={"GET"})
     */
    public function getCurrencies(Request $request, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        $criteria = $this->createCriteria($request, CurrencyDefinition::class, $context);
        $currencies = $this->currencyRepository->search($criteria, $context->getContext());

        return $responseFactory->createListingResponse(
            $currencies,
            CurrencyDefinition::class,
            $request,
            $context->getContext()
        );
    }

    /**
     * @Route("/storefront-api/v{version}/language", name="storefront-api.language.list", methods={"GET"})
     */
    public function getLanguages(Request $request, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        $criteria = $this->createCriteria($request, LanguageDefinition::class, $context);
        $languages = $this->languageRepository->search($criteria, $context->getContext());

        return $responseFactory->createListingResponse(
            $languages,
            LanguageDefinition::class,
            $request,
            $context->getContext()
        );
    }

    /**
     * @Route("/storefront-api/v{version}/country", name="storefront-api.country.list", methods={"GET"})
     */
    public function getCountries(Request $request, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        $criteria = $this->createCriteria($request, CountryDefinition::class, $context);
        $countries = $this->countryRepository->search($criteria, $context->getContext());

        return $responseFactory->createListingResponse(
            $countries,
            CountryDefinition::class,
            $request,
            $context->getContext()
        );
    }

    /**
     * @Route("/storefront-api/v{version}/country/{countryId}/state", name="storefront-api.country.state.list", methods={"GET"})
     *
     * @throws InvalidParameterException
     */
    public function getCountryStates(string $countryId, Request $request, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        if (!Uuid::isValid($countryId)) {
            throw new InvalidParameterException($countryId);
        }
        $limit = $request->query->getInt('limit', 10);
        $page = $request->query->getInt('page', 1);

        --$page;

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('country_state.country.salesChannels.id', $context->getSalesChannel()->getId()));
        $criteria->addFilter(new EqualsFilter('country_state.country.id', $countryId));
        $criteria->setLimit($limit);
        $criteria->setOffset($page * $limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);

        $countryStates = $this->countryStateRepository->search($criteria, $context->getContext());

        return $responseFactory->createListingResponse(
            $countryStates,
            CountryStateDefinition::class,
            $request,
            $context->getContext()
        );
    }

    /**
     * @Route("/storefront-api/v{version}/payment-method", name="storefront-api.payment-method.list", methods={"GET"})
     */
    public function getPaymentMethods(Request $request, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        $criteria = $this->createCriteria($request, PaymentMethodDefinition::class, $context);
        $paymentMethods = $this->filterMethodsByRules($criteria, $context, $this->paymentMethodRepository);

        return $responseFactory->createListingResponse(
            $paymentMethods,
            PaymentMethodDefinition::class,
            $request,
            $context->getContext()
        );
    }

    /**
     * @Route("/storefront-api/v{version}/shipping-method", name="storefront-api.shipping-method.list", methods={"GET"})
     */
    public function getShippingMethods(Request $request, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        $criteria = $this->createCriteria($request, ShippingMethodDefinition::class, $context);
        $shippingMethods = $this->filterMethodsByRules($criteria, $context, $this->shippingMethodRepository);

        return $responseFactory->createListingResponse(
            $shippingMethods,
            ShippingMethodDefinition::class,
            $request,
            $context->getContext()
        );
    }

    private function filterMethodsByRules(
        Criteria $criteria,
        CheckoutContext $context,
        EntityRepositoryInterface $entityRepository
    ): EntitySearchResult {
        $searchResult = $entityRepository->search($criteria, $context->getContext());
        /** @var ShippingMethodCollection|PaymentMethodCollection $entities */
        $entities = $searchResult->getEntities();
        $entities = $entities->filterByActiveRules($context);

        return new EntitySearchResult(
            $searchResult->getTotal() - ($searchResult->getEntities()->count() - $entities->count()),
            $entities,
            $searchResult->getAggregations(),
            $searchResult->getCriteria(),
            $searchResult->getContext()
        );
    }

    private function createCriteria(Request $request, string $definition, CheckoutContext $context): Criteria
    {
        $limit = $request->query->getInt('limit', 10);
        $page = $request->query->getInt('page', 1);

        --$page;

        $criteria = new Criteria();

        /* @var EntityDefinition $definition */
        $criteria->addFilter(new EqualsFilter($definition::getEntityName() . '.salesChannels.id', $context->getSalesChannel()->getId()));
        $criteria->setLimit($limit);
        $criteria->setOffset($page * $limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);

        return $criteria;
    }
}
