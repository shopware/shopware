<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Storefront;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Exception\InvalidParameterException;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Language\LanguageDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StorefrontSalesChannelController extends Controller
{
    /**
     * @var RepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var RepositoryInterface
     */
    private $languageRepository;

    /**
     * @var RepositoryInterface
     */
    private $countryRepository;

    /**
     * @var RepositoryInterface
     */
    private $countryStateRepository;

    /**
     * @var RepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var RepositoryInterface
     */
    private $shippingMethodRepository;

    public function __construct(
        RepositoryInterface $currencyRepository,
        RepositoryInterface $languageRepository,
        RepositoryInterface $countryRepository,
        RepositoryInterface $countryStateRepository,
        RepositoryInterface $paymentMethodRepository,
        RepositoryInterface $shippingMethodRepository
    ) {
        $this->currencyRepository = $currencyRepository;
        $this->languageRepository = $languageRepository;
        $this->countryRepository = $countryRepository;
        $this->countryStateRepository = $countryStateRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
    }

    /**
     * @Route("/storefront-api/sales-channel/currencies", name="storefront.api.sales-channel.currencies", methods={"GET"})
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
     * @Route("/storefront-api/sales-channel/languages", name="storefront.api.sales-channel.languages", methods={"GET"})
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
     * @Route("/storefront-api/sales-channel/countries", name="storefront.api.sales-channel.countries", methods={"GET"})
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
     * @Route("/storefront-api/sales-channel/country/states", name="storefront.api.sales-channel.country.states", methods={"GET"})
     *
     * @throws InvalidParameterException
     */
    public function getCountryStates(Request $request, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        $criteria = $this->createCountryStates($request, $context);
        $countryStates = $this->countryStateRepository->search($criteria, $context->getContext());

        return $responseFactory->createListingResponse(
            $countryStates,
            CountryStateDefinition::class,
            $request,
            $context->getContext()
        );
    }

    /**
     * @Route("/storefront-api/sales-channel/payment-methods", name="storefront.api.sales-channel.payment-methods", methods={"GET"})
     */
    public function getPaymentMethods(Request $request, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        $criteria = $this->createCriteria($request, PaymentMethodDefinition::class, $context);
        $paymentMethods = $this->paymentMethodRepository->search($criteria, $context->getContext());

        return $responseFactory->createListingResponse(
            $paymentMethods,
            PaymentMethodDefinition::class,
            $request,
            $context->getContext()
        );
    }

    /**
     * @Route("/storefront-api/sales-channel/shipping-methods", name="storefront.api.sales-channel.shipping-methods", methods={"GET"})
     */
    public function getShippingMethods(Request $request, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        $criteria = $this->createCriteria($request, ShippingMethodDefinition::class, $context);
        $shippingMethods = $this->shippingMethodRepository->search($criteria, $context->getContext());

        return $responseFactory->createListingResponse(
            $shippingMethods,
            ShippingMethodDefinition::class,
            $request,
            $context->getContext()
        );
    }

    private function createCriteria(Request $request, string $definition, CheckoutContext $context): Criteria
    {
        $limit = $request->query->getInt('limit', 10);
        $page = $request->query->getInt('page', 1);

        --$page;

        $criteria = new Criteria();

        /* @var EntityDefinition $definition */
        $criteria->addFilter(new TermQuery($definition::getEntityName() . '.salesChannels.id', $context->getSalesChannel()->getId()));
        $criteria->setLimit($limit);
        $criteria->setOffset($page * $limit);
        $criteria->setFetchCount(Criteria::FETCH_COUNT_NEXT_PAGES);

        return $criteria;
    }

    /**
     * @throws InvalidParameterException
     */
    private function createCountryStates(Request $request, CheckoutContext $context): Criteria
    {
        $countryId = $request->query->get('countryId');

        if (!Uuid::isValid($countryId)) {
            throw new InvalidParameterException($countryId);
        }
        $limit = $request->query->getInt('limit', 10);
        $page = $request->query->getInt('page', 1);

        --$page;

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('country_state.country.salesChannels.id', $context->getSalesChannel()->getId()));
        $criteria->addFilter(new TermQuery('country_state.country.id', $countryId));
        $criteria->setLimit($limit);
        $criteria->setOffset($page * $limit);
        $criteria->setFetchCount(Criteria::FETCH_COUNT_NEXT_PAGES);

        return $criteria;
    }
}
