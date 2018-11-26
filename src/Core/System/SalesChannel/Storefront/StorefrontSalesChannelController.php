<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Storefront;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
     * @Route("/storefront-api/sales-channel/currencies", name="storefront.api.sales-channel.currencies.deprecated", methods={"GET"})
     *
     * @deprecated
     */
    public function getCurrenciesDeprecated(Request $request, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        return $this->getCurrencies($request, $context, $responseFactory);
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
     * @Route("/storefront-api/sales-channel/languages", name="storefront.api.sales-channel.languages.deprecated", methods={"GET"})
     *
     * @deprecated
     */
    public function getLanguagesDeprecated(Request $request, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        return $this->getLanguages($request, $context, $responseFactory);
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
     * @Route("/storefront-api/sales-channel/countries", name="storefront.api.sales-channel.countries.deprecated", methods={"GET"})
     *
     * @deprecated
     */
    public function getCountriesDeprecated(Request $request, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        return $this->getCountries($request, $context, $responseFactory);
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
     * @Route("/storefront-api/sales-channel/country/states", name="storefront.api.sales-channel.country.states.deprecated", methods={"GET"})
     *
     * @deprecated
     */
    public function getCountryStatesDeprecated(Request $request, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        $countryId = $request->query->get('countryId');

        if (!Uuid::isValid($countryId)) {
            throw new InvalidParameterException($countryId);
        }

        return $this->getCountryStates($countryId, $request, $context, $responseFactory);
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
     * @Route("/storefront-api/sales-channel/payment-methods", name="storefront.api.sales-channel.payment-methods.deprecated", methods={"GET"})
     *
     * @deprecated
     */
    public function getPaymentMethodsDeprecated(Request $request, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        return $this->getPaymentMethods($request, $context, $responseFactory);
    }

    /**
     * @Route("/storefront-api/v{version}/payment-method", name="storefront-api.payment-method.list", methods={"GET"})
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
     * @Route("/storefront-api/sales-channel/shipping-methods", name="storefront.api.sales-channel.shipping-methods.deprecated", methods={"GET"})
     *
     * @deprecated
     */
    public function getShippingMethodsDeprecated(Request $request, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        return $this->getShippingMethods($request, $context, $responseFactory);
    }

    /**
     * @Route("/storefront-api/v{version}/shipping-method", name="storefront-api.shipping-method.list", methods={"GET"})
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
        $criteria->addFilter(new EqualsFilter($definition::getEntityName() . '.salesChannels.id', $context->getSalesChannel()->getId()));
        $criteria->setLimit($limit);
        $criteria->setOffset($page * $limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);

        return $criteria;
    }
}
