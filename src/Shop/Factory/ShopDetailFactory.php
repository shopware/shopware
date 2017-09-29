<?php declare(strict_types=1);

namespace Shopware\Shop\Factory;

use Doctrine\DBAL\Connection;
use Shopware\AreaCountry\Factory\AreaCountryBasicFactory;
use Shopware\AreaCountry\Struct\AreaCountryBasicStruct;
use Shopware\Category\Factory\CategoryBasicFactory;
use Shopware\Category\Struct\CategoryBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Factory\CurrencyBasicFactory;
use Shopware\CustomerGroup\Factory\CustomerGroupBasicFactory;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicStruct;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Locale\Factory\LocaleBasicFactory;
use Shopware\Locale\Struct\LocaleBasicStruct;
use Shopware\PaymentMethod\Factory\PaymentMethodBasicFactory;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\ShippingMethod\Factory\ShippingMethodBasicFactory;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicStruct;
use Shopware\Shop\Struct\ShopBasicStruct;
use Shopware\Shop\Struct\ShopDetailStruct;
use Shopware\ShopTemplate\Factory\ShopTemplateBasicFactory;
use Shopware\ShopTemplate\Struct\ShopTemplateBasicStruct;

class ShopDetailFactory extends ShopBasicFactory
{
    /**
     * @var LocaleBasicFactory
     */
    protected $localeFactory;

    /**
     * @var CategoryBasicFactory
     */
    protected $categoryFactory;

    /**
     * @var CustomerGroupBasicFactory
     */
    protected $customerGroupFactory;

    /**
     * @var PaymentMethodBasicFactory
     */
    protected $paymentMethodFactory;

    /**
     * @var ShippingMethodBasicFactory
     */
    protected $shippingMethodFactory;

    /**
     * @var AreaCountryBasicFactory
     */
    protected $areaCountryFactory;

    /**
     * @var ShopTemplateBasicFactory
     */
    protected $shopTemplateFactory;

    /**
     * @var CurrencyBasicFactory
     */
    protected $currencyFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        LocaleBasicFactory $localeFactory,
        CategoryBasicFactory $categoryFactory,
        CustomerGroupBasicFactory $customerGroupFactory,
        PaymentMethodBasicFactory $paymentMethodFactory,
        ShippingMethodBasicFactory $shippingMethodFactory,
        AreaCountryBasicFactory $areaCountryFactory,
        ShopTemplateBasicFactory $shopTemplateFactory,
        CurrencyBasicFactory $currencyFactory
    ) {
        parent::__construct($connection, $registry, $currencyFactory, $localeFactory);
        $this->localeFactory = $localeFactory;
        $this->categoryFactory = $categoryFactory;
        $this->customerGroupFactory = $customerGroupFactory;
        $this->paymentMethodFactory = $paymentMethodFactory;
        $this->shippingMethodFactory = $shippingMethodFactory;
        $this->areaCountryFactory = $areaCountryFactory;
        $this->shopTemplateFactory = $shopTemplateFactory;
        $this->currencyFactory = $currencyFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());
        $fields['fallbackLocale'] = $this->localeFactory->getFields();
        $fields['category'] = $this->categoryFactory->getFields();
        $fields['customerGroup'] = $this->customerGroupFactory->getFields();
        $fields['paymentMethod'] = $this->paymentMethodFactory->getFields();
        $fields['shippingMethod'] = $this->shippingMethodFactory->getFields();
        $fields['country'] = $this->areaCountryFactory->getFields();
        $fields['template'] = $this->shopTemplateFactory->getFields();
        $fields['_sub_select_availableCurrency_uuids'] = '_sub_select_availableCurrency_uuids';

        return $fields;
    }

    public function hydrate(
        array $data,
        ShopBasicStruct $shop,
        QuerySelection $selection,
        TranslationContext $context
    ): ShopBasicStruct {
        /** @var ShopDetailStruct $shop */
        $shop = parent::hydrate($data, $shop, $selection, $context);
        $locale = $selection->filter('fallbackLocale');
        if ($locale && !empty($data[$locale->getField('uuid')])) {
            $shop->setFallbackLocale(
                $this->localeFactory->hydrate($data, new LocaleBasicStruct(), $locale, $context)
            );
        }
        $category = $selection->filter('category');
        if ($category && !empty($data[$category->getField('uuid')])) {
            $shop->setCategory(
                $this->categoryFactory->hydrate($data, new CategoryBasicStruct(), $category, $context)
            );
        }
        $customerGroup = $selection->filter('customerGroup');
        if ($customerGroup && !empty($data[$customerGroup->getField('uuid')])) {
            $shop->setCustomerGroup(
                $this->customerGroupFactory->hydrate($data, new CustomerGroupBasicStruct(), $customerGroup, $context)
            );
        }
        $paymentMethod = $selection->filter('paymentMethod');
        if ($paymentMethod && !empty($data[$paymentMethod->getField('uuid')])) {
            $shop->setPaymentMethod(
                $this->paymentMethodFactory->hydrate($data, new PaymentMethodBasicStruct(), $paymentMethod, $context)
            );
        }
        $shippingMethod = $selection->filter('shippingMethod');
        if ($shippingMethod && !empty($data[$shippingMethod->getField('uuid')])) {
            $shop->setShippingMethod(
                $this->shippingMethodFactory->hydrate($data, new ShippingMethodBasicStruct(), $shippingMethod, $context)
            );
        }
        $areaCountry = $selection->filter('country');
        if ($areaCountry && !empty($data[$areaCountry->getField('uuid')])) {
            $shop->setCountry(
                $this->areaCountryFactory->hydrate($data, new AreaCountryBasicStruct(), $areaCountry, $context)
            );
        }
        $shopTemplate = $selection->filter('template');
        if ($shopTemplate && !empty($data[$shopTemplate->getField('uuid')])) {
            $shop->setTemplate(
                $this->shopTemplateFactory->hydrate($data, new ShopTemplateBasicStruct(), $shopTemplate, $context)
            );
        }
        if ($selection->hasField('_sub_select_availableCurrency_uuids')) {
            $uuids = explode('|', (string) $data[$selection->getField('_sub_select_availableCurrency_uuids')]);
            $shop->setAvailableCurrencyUuids(array_values(array_filter($uuids)));
        }

        return $shop;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        $this->joinFallbackLocale($selection, $query, $context);
        $this->joinCategory($selection, $query, $context);
        $this->joinCustomerGroup($selection, $query, $context);
        $this->joinPaymentMethod($selection, $query, $context);
        $this->joinShippingMethod($selection, $query, $context);
        $this->joinCountry($selection, $query, $context);
        $this->joinTemplate($selection, $query, $context);
        $this->joinAvailableCurrencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['fallbackLocale'] = $this->localeFactory->getAllFields();
        $fields['category'] = $this->categoryFactory->getAllFields();
        $fields['customerGroup'] = $this->customerGroupFactory->getAllFields();
        $fields['paymentMethod'] = $this->paymentMethodFactory->getAllFields();
        $fields['shippingMethod'] = $this->shippingMethodFactory->getAllFields();
        $fields['country'] = $this->areaCountryFactory->getAllFields();
        $fields['template'] = $this->shopTemplateFactory->getAllFields();
        $fields['availableCurrencies'] = $this->currencyFactory->getAllFields();

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

    private function joinFallbackLocale(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($locale = $selection->filter('fallbackLocale'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'locale',
            $locale->getRootEscaped(),
            sprintf('%s.uuid = %s.fallback_locale_uuid', $locale->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->localeFactory->joinDependencies($locale, $query, $context);
    }

    private function joinCategory(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($category = $selection->filter('category'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'category',
            $category->getRootEscaped(),
            sprintf('%s.uuid = %s.category_uuid', $category->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->categoryFactory->joinDependencies($category, $query, $context);
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

    private function joinPaymentMethod(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($paymentMethod = $selection->filter('paymentMethod'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'payment_method',
            $paymentMethod->getRootEscaped(),
            sprintf('%s.uuid = %s.payment_method_uuid', $paymentMethod->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->paymentMethodFactory->joinDependencies($paymentMethod, $query, $context);
    }

    private function joinShippingMethod(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($shippingMethod = $selection->filter('shippingMethod'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'shipping_method',
            $shippingMethod->getRootEscaped(),
            sprintf('%s.uuid = %s.shipping_method_uuid', $shippingMethod->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->shippingMethodFactory->joinDependencies($shippingMethod, $query, $context);
    }

    private function joinCountry(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($areaCountry = $selection->filter('country'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'area_country',
            $areaCountry->getRootEscaped(),
            sprintf('%s.uuid = %s.area_country_uuid', $areaCountry->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->areaCountryFactory->joinDependencies($areaCountry, $query, $context);
    }

    private function joinTemplate(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($shopTemplate = $selection->filter('template'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'shop_template',
            $shopTemplate->getRootEscaped(),
            sprintf('%s.uuid = %s.shop_template_uuid', $shopTemplate->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->shopTemplateFactory->joinDependencies($shopTemplate, $query, $context);
    }

    private function joinAvailableCurrencies(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if ($selection->hasField('_sub_select_availableCurrency_uuids')) {
            $query->addSelect('
                (
                    SELECT GROUP_CONCAT(mapping.currency_uuid SEPARATOR \'|\')
                    FROM shop_currency mapping
                    WHERE mapping.shop_uuid = ' . $selection->getRootEscaped() . '.uuid
                ) as ' . QuerySelection::escape($selection->getField('_sub_select_availableCurrency_uuids'))
            );
        }

        if (!($availableCurrencies = $selection->filter('availableCurrencies'))) {
            return;
        }

        $mapping = QuerySelection::escape($availableCurrencies->getRoot() . '.mapping');

        $query->leftJoin(
            $selection->getRootEscaped(),
            'shop_currency',
            $mapping,
            sprintf('%s.uuid = %s.shop_uuid', $selection->getRootEscaped(), $mapping)
        );
        $query->leftJoin(
            $mapping,
            'currency',
            $availableCurrencies->getRootEscaped(),
            sprintf('%s.currency_uuid = %s.uuid', $mapping, $availableCurrencies->getRootEscaped())
        );

        $this->currencyFactory->joinDependencies($availableCurrencies, $query, $context);

        $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
    }
}
