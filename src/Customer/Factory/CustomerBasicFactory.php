<?php declare(strict_types=1);

namespace Shopware\Customer\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Extension\CustomerExtension;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\CustomerAddress\Factory\CustomerAddressBasicFactory;
use Shopware\CustomerAddress\Struct\CustomerAddressBasicStruct;
use Shopware\CustomerGroup\Factory\CustomerGroupBasicFactory;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicStruct;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\PaymentMethod\Factory\PaymentMethodBasicFactory;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class CustomerBasicFactory extends Factory
{
    const ROOT_NAME = 'customer';
    const EXTENSION_NAMESPACE = 'customer';

    const FIELDS = [
       'uuid' => 'uuid',
       'number' => 'customer_number',
       'salutation' => 'salutation',
       'firstName' => 'first_name',
       'lastName' => 'last_name',
       'password' => 'password',
       'email' => 'email',
       'groupUuid' => 'customer_group_uuid',
       'defaultPaymentMethodUuid' => 'default_payment_method_uuid',
       'shopUuid' => 'shop_uuid',
       'mainShopUuid' => 'main_shop_uuid',
       'title' => 'title',
       'encoder' => 'encoder',
       'active' => 'active',
       'accountMode' => 'account_mode',
       'confirmationKey' => 'confirmation_key',
       'lastPaymentMethodUuid' => 'last_payment_method_uuid',
       'firstLogin' => 'first_login',
       'lastLogin' => 'last_login',
       'sessionId' => 'session_id',
       'newsletter' => 'newsletter',
       'validation' => 'validation',
       'affiliate' => 'affiliate',
       'referer' => 'referer',
       'internalComment' => 'internal_comment',
       'failedLogins' => 'failed_logins',
       'lockedUntil' => 'locked_until',
       'defaultBillingAddressUuid' => 'default_billing_address_uuid',
       'defaultShippingAddressUuid' => 'default_shipping_address_uuid',
       'birthday' => 'birthday',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
    ];

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
        ExtensionRegistryInterface $registry,
        CustomerGroupBasicFactory $customerGroupFactory,
        CustomerAddressBasicFactory $customerAddressFactory,
        PaymentMethodBasicFactory $paymentMethodFactory
    ) {
        parent::__construct($connection, $registry);
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
        $customer->setNumber((string) $data[$selection->getField('number')]);
        $customer->setSalutation((string) $data[$selection->getField('salutation')]);
        $customer->setFirstName((string) $data[$selection->getField('firstName')]);
        $customer->setLastName((string) $data[$selection->getField('lastName')]);
        $customer->setPassword((string) $data[$selection->getField('password')]);
        $customer->setEmail((string) $data[$selection->getField('email')]);
        $customer->setGroupUuid((string) $data[$selection->getField('groupUuid')]);
        $customer->setDefaultPaymentMethodUuid((string) $data[$selection->getField('defaultPaymentMethodUuid')]);
        $customer->setShopUuid((string) $data[$selection->getField('shopUuid')]);
        $customer->setMainShopUuid((string) $data[$selection->getField('mainShopUuid')]);
        $customer->setTitle(isset($data[$selection->getField('title')]) ? (string) $data[$selection->getField('title')] : null);
        $customer->setEncoder((string) $data[$selection->getField('encoder')]);
        $customer->setActive((bool) $data[$selection->getField('active')]);
        $customer->setAccountMode((int) $data[$selection->getField('accountMode')]);
        $customer->setConfirmationKey(isset($data[$selection->getField('confirmationKey')]) ? (string) $data[$selection->getField('confirmationKey')] : null);
        $customer->setLastPaymentMethodUuid(isset($data[$selection->getField('lastPaymentMethodUuid')]) ? (string) $data[$selection->getField('lastPaymentMethodUuid')] : null);
        $customer->setFirstLogin(isset($data[$selection->getField('firstLogin')]) ? new \DateTime($data[$selection->getField('firstLogin')]) : null);
        $customer->setLastLogin(isset($data[$selection->getField('lastLogin')]) ? new \DateTime($data[$selection->getField('lastLogin')]) : null);
        $customer->setSessionId(isset($data[$selection->getField('sessionId')]) ? (string) $data[$selection->getField('sessionId')] : null);
        $customer->setNewsletter((bool) $data[$selection->getField('newsletter')]);
        $customer->setValidation(isset($data[$selection->getField('validation')]) ? (string) $data[$selection->getField('validation')] : null);
        $customer->setAffiliate(isset($data[$selection->getField('affiliate')]) ? (bool) $data[$selection->getField('affiliate')] : null);
        $customer->setReferer(isset($data[$selection->getField('referer')]) ? (string) $data[$selection->getField('referer')] : null);
        $customer->setInternalComment(isset($data[$selection->getField('internalComment')]) ? (string) $data[$selection->getField('internalComment')] : null);
        $customer->setFailedLogins((int) $data[$selection->getField('failedLogins')]);
        $customer->setLockedUntil(isset($data[$selection->getField('lockedUntil')]) ? new \DateTime($data[$selection->getField('lockedUntil')]) : null);
        $customer->setDefaultBillingAddressUuid(isset($data[$selection->getField('defaultBillingAddressUuid')]) ? (string) $data[$selection->getField('defaultBillingAddressUuid')] : null);
        $customer->setDefaultShippingAddressUuid(isset($data[$selection->getField('defaultShippingAddressUuid')]) ? (string) $data[$selection->getField('defaultShippingAddressUuid')] : null);
        $customer->setBirthday(isset($data[$selection->getField('birthday')]) ? new \DateTime($data[$selection->getField('birthday')]) : null);
        $customer->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $customer->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
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

        /** @var $extension CustomerExtension */
        foreach ($this->getExtensions() as $extension) {
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
        $this->joinCustomerGroup($selection, $query, $context);
        $this->joinDefaultShippingAddress($selection, $query, $context);
        $this->joinDefaultBillingAddress($selection, $query, $context);
        $this->joinLastPaymentMethod($selection, $query, $context);
        $this->joinDefaultPaymentMethod($selection, $query, $context);
        $this->joinTranslation($selection, $query, $context);

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

    protected function getExtensionNamespace(): string
    {
        return self::EXTENSION_NAMESPACE;
    }

    private function joinCustomerGroup(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($customerGroup = $selection->filter('customerGroup'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'customer_group',
            $customerGroup->getRootEscaped(),
            sprintf('%s.uuid = %s.customer_group_uuid', $customerGroup->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->customerGroupFactory->joinDependencies($customerGroup, $query, $context);
    }

    private function joinDefaultShippingAddress(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($customerAddress = $selection->filter('defaultShippingAddress'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'customer_address',
            $customerAddress->getRootEscaped(),
            sprintf('%s.uuid = %s.default_shipping_address_uuid', $customerAddress->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->customerAddressFactory->joinDependencies($customerAddress, $query, $context);
    }

    private function joinDefaultBillingAddress(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($customerAddress = $selection->filter('defaultBillingAddress'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'customer_address',
            $customerAddress->getRootEscaped(),
            sprintf('%s.uuid = %s.default_billing_address_uuid', $customerAddress->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->customerAddressFactory->joinDependencies($customerAddress, $query, $context);
    }

    private function joinLastPaymentMethod(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($paymentMethod = $selection->filter('lastPaymentMethod'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'payment_method',
            $paymentMethod->getRootEscaped(),
            sprintf('%s.uuid = %s.last_payment_method_uuid', $paymentMethod->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->paymentMethodFactory->joinDependencies($paymentMethod, $query, $context);
    }

    private function joinDefaultPaymentMethod(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($paymentMethod = $selection->filter('defaultPaymentMethod'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'payment_method',
            $paymentMethod->getRootEscaped(),
            sprintf('%s.uuid = %s.default_payment_method_uuid', $paymentMethod->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->paymentMethodFactory->joinDependencies($paymentMethod, $query, $context);
    }

    private function joinTranslation(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($translation = $selection->filter('translation'))) {
            return;
        }
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
}
