<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\SalesChannel\Service;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class SalesChannelCreator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly EntityRepository $shippingMethodRepository,
        private readonly EntityRepository $countryRepository,
        private readonly EntityRepository $categoryRepository
    ) {
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

        $languageId ??= Defaults::LANGUAGE_SYSTEM;
        $currencyId ??= Defaults::CURRENCY;
        $paymentMethodId ??= $this->getFirstActivePaymentMethodId($context);
        $shippingMethodId ??= $this->getFirstActiveShippingMethodId($context);
        $countryId ??= $this->getFirstActiveCountryId($context);

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
            'customerGroupId' => $customerGroupId ?? $this->getCustomerGroupId($context),
            'navigationCategoryId' => $navigationCategoryId ?? $this->getRootCategoryId($context),

            // available mappings
            'currencies' => $currencies,
            'languages' => $languages,
            'shippingMethods' => $shippingMethods,
            'paymentMethods' => $paymentMethods,
            'countries' => $countries,
        ];

        $data = array_replace_recursive($data, $overwrites);

        $this->salesChannelRepository->create([$data], $context);

        return $data['accessKey'];
    }

    private function getFirstActiveShippingMethodId(Context $context): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true));

        /** @var array<string> $ids */
        $ids = $this->shippingMethodRepository->searchIds($criteria, $context)->getIds();

        return $ids[0];
    }

    private function getFirstActivePaymentMethodId(Context $context): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('position'));

        /** @var array<string> $ids */
        $ids = $this->paymentMethodRepository->searchIds($criteria, $context)->getIds();

        return $ids[0];
    }

    private function getFirstActiveCountryId(Context $context): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('position'));

        /** @var array<string> $ids */
        $ids = $this->countryRepository->searchIds($criteria, $context)->getIds();

        return $ids[0];
    }

    private function getRootCategoryId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('category.parentId', null));
        $criteria->addSorting(new FieldSorting('category.createdAt', FieldSorting::ASCENDING));

        /** @var array<string> $categories */
        $categories = $this->categoryRepository->searchIds($criteria, $context)->getIds();

        return $categories[0];
    }

    private function getAllIdsOf(string $entity, Context $context): array
    {
        /** @var array<string> $ids */
        $ids = $this->definitionRegistry->getRepository($entity)->searchIds(new Criteria(), $context)->getIds();

        return array_map(
            fn (string $id) => ['id' => $id],
            $ids
        );
    }

    private function getCustomerGroupId(Context $context): string
    {
        $criteria = (new Criteria())
            ->setLimit(1);

        $repository = $this->definitionRegistry->getRepository(CustomerGroupDefinition::ENTITY_NAME);

        $id = $repository->searchIds($criteria, $context)->firstId();

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
            fn (string $id) => ['id' => $id],
            $values
        );
    }
}
