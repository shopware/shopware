<?php declare(strict_types=1);

namespace Shopware\Shop\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Factory\CurrencyBasicFactory;
use Shopware\Currency\Struct\CurrencyBasicStruct;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\Locale\Factory\LocaleBasicFactory;
use Shopware\Locale\Struct\LocaleBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\Shop\Extension\ShopExtension;
use Shopware\Shop\Struct\ShopBasicStruct;

class ShopBasicFactory extends Factory
{
    const ROOT_NAME = 'shop';
    const EXTENSION_NAMESPACE = 'shop';

    const FIELDS = [
       'uuid' => 'uuid',
       'name' => 'name',
       'title' => 'title',
       'position' => 'position',
       'host' => 'host',
       'basePath' => 'base_path',
       'baseUrl' => 'base_url',
       'hosts' => 'hosts',
       'isSecure' => 'is_secure',
       'customerScope' => 'customer_scope',
       'isDefault' => 'is_default',
       'active' => 'active',
       'taxCalculationType' => 'tax_calculation_type',
       'parentUuid' => 'parent_uuid',
       'templateUuid' => 'shop_template_uuid',
       'documentTemplateUuid' => 'document_template_uuid',
       'categoryUuid' => 'category_uuid',
       'localeUuid' => 'locale_uuid',
       'currencyUuid' => 'currency_uuid',
       'customerGroupUuid' => 'customer_group_uuid',
       'fallbackLocaleUuid' => 'fallback_locale_uuid',
       'paymentMethodUuid' => 'payment_method_uuid',
       'shippingMethodUuid' => 'shipping_method_uuid',
       'areaCountryUuid' => 'area_country_uuid',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
    ];

    /**
     * @var CurrencyBasicFactory
     */
    protected $currencyFactory;

    /**
     * @var LocaleBasicFactory
     */
    protected $localeFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        CurrencyBasicFactory $currencyFactory,
        LocaleBasicFactory $localeFactory
    ) {
        parent::__construct($connection, $registry);
        $this->currencyFactory = $currencyFactory;
        $this->localeFactory = $localeFactory;
    }

    public function hydrate(
        array $data,
        ShopBasicStruct $shop,
        QuerySelection $selection,
        TranslationContext $context
    ): ShopBasicStruct {
        $shop->setUuid((string) $data[$selection->getField('uuid')]);
        $shop->setName((string) $data[$selection->getField('name')]);
        $shop->setTitle(isset($data[$selection->getField('title')]) ? (string) $data[$selection->getField('title')] : null);
        $shop->setPosition((int) $data[$selection->getField('position')]);
        $shop->setHost((string) $data[$selection->getField('host')]);
        $shop->setBasePath((string) $data[$selection->getField('basePath')]);
        $shop->setBaseUrl((string) $data[$selection->getField('baseUrl')]);
        $shop->setHosts(isset($data[$selection->getField('hosts')]) ? (string) $data[$selection->getField('hosts')] : null);
        $shop->setIsSecure((bool) $data[$selection->getField('isSecure')]);
        $shop->setCustomerScope((bool) $data[$selection->getField('customerScope')]);
        $shop->setIsDefault((bool) $data[$selection->getField('isDefault')]);
        $shop->setActive((bool) $data[$selection->getField('active')]);
        $shop->setTaxCalculationType((string) $data[$selection->getField('taxCalculationType')]);
        $shop->setParentUuid(isset($data[$selection->getField('parentUuid')]) ? (string) $data[$selection->getField('parentUuid')] : null);
        $shop->setTemplateUuid((string) $data[$selection->getField('templateUuid')]);
        $shop->setDocumentTemplateUuid((string) $data[$selection->getField('documentTemplateUuid')]);
        $shop->setCategoryUuid((string) $data[$selection->getField('categoryUuid')]);
        $shop->setLocaleUuid((string) $data[$selection->getField('localeUuid')]);
        $shop->setCurrencyUuid((string) $data[$selection->getField('currencyUuid')]);
        $shop->setCustomerGroupUuid((string) $data[$selection->getField('customerGroupUuid')]);
        $shop->setFallbackLocaleUuid(isset($data[$selection->getField('fallbackLocaleUuid')]) ? (string) $data[$selection->getField('fallbackLocaleUuid')] : null);
        $shop->setPaymentMethodUuid(isset($data[$selection->getField('paymentMethodUuid')]) ? (string) $data[$selection->getField('paymentMethodUuid')] : null);
        $shop->setShippingMethodUuid(isset($data[$selection->getField('shippingMethodUuid')]) ? (string) $data[$selection->getField('shippingMethodUuid')] : null);
        $shop->setAreaCountryUuid(isset($data[$selection->getField('areaCountryUuid')]) ? (string) $data[$selection->getField('areaCountryUuid')] : null);
        $shop->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $shop->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
        $currency = $selection->filter('currency');
        if ($currency && !empty($data[$currency->getField('uuid')])) {
            $shop->setCurrency(
                $this->currencyFactory->hydrate($data, new CurrencyBasicStruct(), $currency, $context)
            );
        }
        $locale = $selection->filter('locale');
        if ($locale && !empty($data[$locale->getField('uuid')])) {
            $shop->setLocale(
                $this->localeFactory->hydrate($data, new LocaleBasicStruct(), $locale, $context)
            );
        }

        /** @var $extension ShopExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($shop, $data, $selection, $context);
        }

        return $shop;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        $fields['currency'] = $this->currencyFactory->getFields();
        $fields['locale'] = $this->localeFactory->getFields();

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        $this->joinCurrency($selection, $query, $context);
        $this->joinLocale($selection, $query, $context);
        $this->joinTranslation($selection, $query, $context);

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());
        $fields['currency'] = $this->currencyFactory->getAllFields();
        $fields['locale'] = $this->localeFactory->getAllFields();

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

    private function joinCurrency(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($currency = $selection->filter('currency'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'currency',
            $currency->getRootEscaped(),
            sprintf('%s.uuid = %s.currency_uuid', $currency->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->currencyFactory->joinDependencies($currency, $query, $context);
    }

    private function joinLocale(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($locale = $selection->filter('locale'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'locale',
            $locale->getRootEscaped(),
            sprintf('%s.uuid = %s.locale_uuid', $locale->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->localeFactory->joinDependencies($locale, $query, $context);
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
            'shop_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.shop_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
