<?php declare(strict_types=1);

namespace Shopware\Customer\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\Customer\Struct\CustomerDetailStruct;
use Shopware\CustomerAddress\Factory\CustomerAddressBasicFactory;
use Shopware\CustomerGroup\Factory\CustomerGroupBasicFactory;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\PaymentMethod\Factory\PaymentMethodBasicFactory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\Shop\Factory\ShopBasicFactory;
use Shopware\Shop\Struct\ShopBasicStruct;

class CustomerDetailFactory extends CustomerBasicFactory
{
    /**
     * @var CustomerAddressBasicFactory
     */
    protected $customerAddressFactory;

    /**
     * @var ShopBasicFactory
     */
    protected $shopFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        CustomerAddressBasicFactory $customerAddressFactory,
        ShopBasicFactory $shopFactory,
        CustomerGroupBasicFactory $customerGroupFactory,
        PaymentMethodBasicFactory $paymentMethodFactory
    ) {
        parent::__construct($connection, $registry, $customerGroupFactory, $customerAddressFactory, $paymentMethodFactory);
        $this->customerAddressFactory = $customerAddressFactory;
        $this->shopFactory = $shopFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());
        $fields['shop'] = $this->shopFactory->getFields();

        return $fields;
    }

    public function hydrate(
        array $data,
        CustomerBasicStruct $customer,
        QuerySelection $selection,
        TranslationContext $context
    ): CustomerBasicStruct {
        /** @var CustomerDetailStruct $customer */
        $customer = parent::hydrate($data, $customer, $selection, $context);
        $shop = $selection->filter('shop');
        if ($shop && !empty($data[$shop->getField('uuid')])) {
            $customer->setShop(
                $this->shopFactory->hydrate($data, new ShopBasicStruct(), $shop, $context)
            );
        }

        return $customer;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        $this->joinAddresses($selection, $query, $context);
        $this->joinShop($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['addresses'] = $this->customerAddressFactory->getAllFields();
        $fields['shop'] = $this->shopFactory->getAllFields();

        return $fields;
    }

    protected function getExtensionFields(): array
    {
        $fields = parent::getExtensionFields();

        foreach ($this->getExtensions() as $extension) {
            $extensionFields = $extension->getDetailFields();
            foreach ($extensionFields as $key => $field) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }

    private function joinAddresses(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($addresses = $selection->filter('addresses'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'customer_address',
            $addresses->getRootEscaped(),
            sprintf('%s.uuid = %s.customer_uuid', $selection->getRootEscaped(), $addresses->getRootEscaped())
        );

        $this->customerAddressFactory->joinDependencies($addresses, $query, $context);

        $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
    }

    private function joinShop(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($shop = $selection->filter('shop'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'shop',
            $shop->getRootEscaped(),
            sprintf('%s.uuid = %s.shop_uuid', $shop->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->shopFactory->joinDependencies($shop, $query, $context);
    }
}
