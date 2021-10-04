<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\SalesChannel\Service;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class SalesChannelCreator
{
    private EntityRepositoryInterface $salesChannelRepository;

    private EntityRepositoryInterface $paymentMethodRepository;

    private EntityRepositoryInterface $shippingMethodRepository;

    private EntityRepositoryInterface $countryRepository;

    private DefinitionInstanceRegistry $definitionRegistry;

    private EntityRepositoryInterface $categoryRepository;

    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $paymentMethodRepository,
        EntityRepositoryInterface $shippingMethodRepository,
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $categoryRepository
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->countryRepository = $countryRepository;
        $this->categoryRepository = $categoryRepository;
    }

    public function createSalesChannel(
        string $id,
        string $name,
        string $typeId,
        ?string $languageId = null,
        ?string $currencyId = null,
        ?string $paymentMethodId = null,
        ?string $shippingMethodId = null,
        ?string $countryId = null,
        ?string $customerGroupId = null,
        ?string $navigationCategoryId = null,
        ?array $currencies = null,
        ?array $languages = null,
        ?array $shippingMethods = null,
        ?array $paymentMethods = null,
        ?array $countries = null,
        array $overwrites = []
    ): string {
        $context = Context::createDefaultContext();

        $languageId = $languageId ?? Defaults::LANGUAGE_SYSTEM;
        $currencyId = $currencyId ?? Defaults::CURRENCY;
        $paymentMethodId = $paymentMethodId ?? $this->getFirstActivePaymentMethodId();
        $shippingMethodId = $shippingMethodId ?? $this->getFirstActiveShippingMethodId();
        $countryId = $countryId ?? $this->getFirstActiveCountryId();

        $currencies = $this->formatToMany($currencies, $currencyId, 'currency', $context);
        $languages = $this->formatToMany($languages, $languageId, 'language', $context);
        $shippingMethods = $this->formatToMany($shippingMethods, $shippingMethodId, 'shipping_method', $context);
        $paymentMethods = $this->formatToMany($paymentMethods, $paymentMethodId, 'payment_method', $context);
        $countries = $this->formatToMany($countries, $countryId, 'country', $context);

        $data = [
            'id' => $id,
            'name' => $name,
            'typeId' => $typeId,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),

            // default selection
            'languageId' => $languageId,
            'currencyId' => $currencyId,
            'paymentMethodId' => $paymentMethodId,
            'shippingMethodId' => $shippingMethodId,
            'countryId' => $countryId,
            'customerGroupId' => $customerGroupId ?? $this->getCustomerGroupId(),
            'navigationCategoryId' => $navigationCategoryId ?? $this->getRootCategoryId(),

            // available mappings
            'currencies' => $currencies,
            'languages' => $languages,
            'shippingMethods' => $shippingMethods,
            'paymentMethods' => $paymentMethods,
            'countries' => $countries,
        ];

        $data = array_replace_recursive($data, $overwrites);

        $this->salesChannelRepository->create([$data], Context::createDefaultContext());

        return $data['accessKey'];
    }

    private function getFirstActiveShippingMethodId(): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true));

        /** @var string[] $ids */
        $ids = $this->shippingMethodRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        return $ids[0];
    }

    private function getFirstActivePaymentMethodId(): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('position'));

        /** @var string[] $ids */
        $ids = $this->paymentMethodRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        return $ids[0];
    }

    private function getFirstActiveCountryId(): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('position'));

        /** @var string[] $ids */
        $ids = $this->countryRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        return $ids[0];
    }

    private function getRootCategoryId(): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('category.parentId', null));
        $criteria->addSorting(new FieldSorting('category.createdAt', FieldSorting::ASCENDING));

        /** @var string[] $categories */
        $categories = $this->categoryRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        return $categories[0];
    }

    private function getAllIdsOf(string $entity, Context $context): array
    {
        $repository = $this->definitionRegistry->getRepository($entity);

        $ids = $repository->searchIds(new Criteria(), $context);

        return array_map(
            function (string $id) {
                return ['id' => $id];
            },
            $ids->getIds()
        );
    }

    private function getCustomerGroupId(): string
    {
        $criteria = (new Criteria())
            ->setLimit(1);

        $repository = $this->definitionRegistry->getRepository(CustomerGroupDefinition::ENTITY_NAME);

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();

        if ($id === null) {
            throw new \RuntimeException('Cannot find a customer group to assign it to the sales channel');
        }

        return $id;
    }

    private function formatToMany(?array $values, string $default, string $entity, Context $context): array
    {
        if (!\is_array($values)) {
            return $this->getAllIdsOf($entity, $context);
        }

        $values = array_unique(array_merge($values, [$default]));

        return array_map(
            function (string $id) {
                return ['id' => $id];
            },
            $values
        );
    }
}
