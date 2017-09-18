<?php

namespace Shopware\Customer\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Extension\CustomerExtension;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\CustomerAddress\Factory\CustomerAddressBasicFactory;
use Shopware\CustomerAddress\Struct\CustomerAddressBasicStruct;
use Shopware\CustomerGroup\Factory\CustomerGroupBasicFactory;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicStruct;
use Shopware\Framework\Factory\Factory;
use Shopware\PaymentMethod\Factory\PaymentMethodBasicFactory;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class CustomerBasicFactory extends Factory
{
    const ROOT_NAME = 'customer';

    const FIELDS = [
       'uuid' => 'uuid',
       'customer_number' => 'customer_number',
       'salutation' => 'salutation',
       'first_name' => 'first_name',
       'last_name' => 'last_name',
       'password' => 'password',
       'email' => 'email',
       'customer_group_uuid' => 'customer_group_uuid',
       'default_payment_method_uuid' => 'default_payment_method_uuid',
       'shop_uuid' => 'shop_uuid',
       'main_shop_uuid' => 'main_shop_uuid',
       'title' => 'title',
       'encoder' => 'encoder',
       'active' => 'active',
       'account_mode' => 'account_mode',
       'confirmation_key' => 'confirmation_key',
       'last_payment_method_uuid' => 'last_payment_method_uuid',
       'first_login' => 'first_login',
       'last_login' => 'last_login',
       'session_id' => 'session_id',
       'newsletter' => 'newsletter',
       'validation' => 'validation',
       'affiliate' => 'affiliate',
       'referer' => 'referer',
       'internal_comment' => 'internal_comment',
       'failed_logins' => 'failed_logins',
       'locked_until' => 'locked_until',
       'default_billing_address_uuid' => 'default_billing_address_uuid',
       'default_shipping_address_uuid' => 'default_shipping_address_uuid',
       'birthday' => 'birthday',
    ];

    /**
     * @var CustomerExtension[]
     */
    protected $extensions = [];

    /**
     * @var CustomerGroupBasicFactory
     */
    protected $customerGroupFactory;

    /**
     * @var CustomerAddressBasicFactory
     */
    protected $customerAddressFactory;

    /**
     * @var PaymentMethodBasicFactory
     */
    protected $paymentMethodFactory;

    public function __construct(
        Connection $connection,
        array $extensions,
        CustomerGroupBasicFactory $customerGroupFactory,
        CustomerAddressBasicFactory $customerAddressFactory,
        PaymentMethodBasicFactory $paymentMethodFactory
    ) {
        parent::__construct($connection, $extensions);
        $this->customerGroupFactory = $customerGroupFactory;
        $this->customerAddressFactory = $customerAddressFactory;
        $this->paymentMethodFactory = $paymentMethodFactory;
    }

    public function hydrate(
        array $data,
        CustomerBasicStruct $customer,
        QuerySelection $selection,
        TranslationContext $context
    ): CustomerBasicStruct {
        $customer->setUuid((string) $data[$selection->getField('uuid')]);
        $customer->setNumber((string) $data[$selection->getField('customer_number')]);
        $customer->setSalutation((string) $data[$selection->getField('salutation')]);
        $customer->setFirstName((string) $data[$selection->getField('first_name')]);
        $customer->setLastName((string) $data[$selection->getField('last_name')]);
        $customer->setPassword((string) $data[$selection->getField('password')]);
        $customer->setEmail((string) $data[$selection->getField('email')]);
        $customer->setGroupUuid((string) $data[$selection->getField('customer_group_uuid')]);
        $customer->setDefaultPaymentMethodUuid((string) $data[$selection->getField('default_payment_method_uuid')]);
        $customer->setShopUuid((string) $data[$selection->getField('shop_uuid')]);
        $customer->setMainShopUuid((string) $data[$selection->getField('main_shop_uuid')]);
        $customer->setTitle(isset($data[$selection->getField('title')]) ? (string) $data[$selection->getField('title')] : null);
        $customer->setEncoder((string) $data[$selection->getField('encoder')]);
        $customer->setActive((bool) $data[$selection->getField('active')]);
        $customer->setAccountMode((int) $data[$selection->getField('account_mode')]);
        $customer->setConfirmationKey(isset($data[$selection->getField('confirmation_key')]) ? (string) $data[$selection->getField('confirmation_key')] : null);
        $customer->setLastPaymentMethodUuid(isset($data[$selection->getField('last_payment_method_uuid')]) ? (string) $data[$selection->getField('last_payment_method_uuid')] : null);
        $customer->setFirstLogin(isset($data[$selection->getField('first_login')]) ? new \DateTime($data[$selection->getField('first_login')]) : null);
        $customer->setLastLogin(isset($data[$selection->getField('last_login')]) ? new \DateTime($data[$selection->getField('last_login')]) : null);
        $customer->setSessionId(isset($data[$selection->getField('session_id')]) ? (string) $data[$selection->getField('session_id')] : null);
        $customer->setNewsletter((bool) $data[$selection->getField('newsletter')]);
        $customer->setValidation(isset($data[$selection->getField('validation')]) ? (string) $data[$selection->getField('validation')] : null);
        $customer->setAffiliate(isset($data[$selection->getField('affiliate')]) ? (bool) $data[$selection->getField('affiliate')] : null);
        $customer->setReferer(isset($data[$selection->getField('referer')]) ? (string) $data[$selection->getField('referer')] : null);
        $customer->setInternalComment(isset($data[$selection->getField('internal_comment')]) ? (string) $data[$selection->getField('internal_comment')] : null);
        $customer->setFailedLogins((int) $data[$selection->getField('failed_logins')]);
        $customer->setLockedUntil(isset($data[$selection->getField('locked_until')]) ? new \DateTime($data[$selection->getField('locked_until')]) : null);
        $customer->setDefaultBillingAddressUuid(isset($data[$selection->getField('default_billing_address_uuid')]) ? (string) $data[$selection->getField('default_billing_address_uuid')] : null);
        $customer->setDefaultShippingAddressUuid(isset($data[$selection->getField('default_shipping_address_uuid')]) ? (string) $data[$selection->getField('default_shipping_address_uuid')] : null);
        $customer->setBirthday(isset($data[$selection->getField('birthday')]) ? new \DateTime($data[$selection->getField('birthday')]) : null);
        $customerGroup = $selection->filter('customerGroup');
        if ($customerGroup && !empty($data[$customerGroup->getField('uuid')])) {
            $customer->setCustomerGroup(
                $this->customerGroupFactory->hydrate($data, new CustomerGroupBasicStruct(), $customerGroup, $context)
            );
        }
        $customerAddress = $selection->filter('defaultShippingAddress');
        if ($customerAddress && !empty($data[$customerAddress->getField('uuid')])) {
            $customer->setDefaultShippingAddress(
                $this->customerAddressFactory->hydrate($data, new CustomerAddressBasicStruct(), $customerAddress, $context)
            );
        }
        $customerAddress = $selection->filter('defaultBillingAddress');
        if ($customerAddress && !empty($data[$customerAddress->getField('uuid')])) {
            $customer->setDefaultBillingAddress(
                $this->customerAddressFactory->hydrate($data, new CustomerAddressBasicStruct(), $customerAddress, $context)
            );
        }
        $paymentMethod = $selection->filter('lastPaymentMethod');
        if ($paymentMethod && !empty($data[$paymentMethod->getField('uuid')])) {
            $customer->setLastPaymentMethod(
                $this->paymentMethodFactory->hydrate($data, new PaymentMethodBasicStruct(), $paymentMethod, $context)
            );
        }
        $paymentMethod = $selection->filter('defaultPaymentMethod');
        if ($paymentMethod && !empty($data[$paymentMethod->getField('uuid')])) {
            $customer->setDefaultPaymentMethod(
                $this->paymentMethodFactory->hydrate($data, new PaymentMethodBasicStruct(), $paymentMethod, $context)
            );
        }

        foreach ($this->extensions as $extension) {
            $extension->hydrate($customer, $data, $selection, $context);
        }

        return $customer;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        $fields['customerGroup'] = $this->customerGroupFactory->getFields();
        $fields['defaultShippingAddress'] = $this->customerAddressFactory->getFields();
        $fields['defaultBillingAddress'] = $this->customerAddressFactory->getFields();
        $fields['lastPaymentMethod'] = $this->paymentMethodFactory->getFields();
        $fields['defaultPaymentMethod'] = $this->paymentMethodFactory->getFields();

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        if ($customerGroup = $selection->filter('customerGroup')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'customer_group',
                $customerGroup->getRootEscaped(),
                sprintf('%s.uuid = %s.customer_group_uuid', $customerGroup->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->customerGroupFactory->joinDependencies($customerGroup, $query, $context);
        }

        if ($customerAddress = $selection->filter('defaultShippingAddress')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'customer_address',
                $customerAddress->getRootEscaped(),
                sprintf('%s.uuid = %s.default_shipping_address_uuid', $customerAddress->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->customerAddressFactory->joinDependencies($customerAddress, $query, $context);
        }

        if ($customerAddress = $selection->filter('defaultBillingAddress')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'customer_address',
                $customerAddress->getRootEscaped(),
                sprintf('%s.uuid = %s.default_billing_address_uuid', $customerAddress->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->customerAddressFactory->joinDependencies($customerAddress, $query, $context);
        }

        if ($paymentMethod = $selection->filter('lastPaymentMethod')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'payment_method',
                $paymentMethod->getRootEscaped(),
                sprintf('%s.uuid = %s.last_payment_method_uuid', $paymentMethod->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->paymentMethodFactory->joinDependencies($paymentMethod, $query, $context);
        }

        if ($paymentMethod = $selection->filter('defaultPaymentMethod')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'payment_method',
                $paymentMethod->getRootEscaped(),
                sprintf('%s.uuid = %s.default_payment_method_uuid', $paymentMethod->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->paymentMethodFactory->joinDependencies($paymentMethod, $query, $context);
        }

        if ($translation = $selection->filter('translation')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'customer_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.customer_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                    $translation->getRootEscaped(),
                    $selection->getRootEscaped(),
                    $translation->getRootEscaped()
                )
            );
            $query->setParameter('languageUuid', $context->getShopUuid());
        }

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());
        $fields['customerGroup'] = $this->customerGroupFactory->getAllFields();
        $fields['defaultShippingAddress'] = $this->customerAddressFactory->getAllFields();
        $fields['defaultBillingAddress'] = $this->customerAddressFactory->getAllFields();
        $fields['lastPaymentMethod'] = $this->paymentMethodFactory->getAllFields();
        $fields['defaultPaymentMethod'] = $this->paymentMethodFactory->getAllFields();

        return $fields;
    }

    protected function getRootName(): string
    {
        return self::ROOT_NAME;
    }
}
