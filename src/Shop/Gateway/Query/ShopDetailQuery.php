<?php

namespace Shopware\Shop\Gateway\Query;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\FieldHelper;
use Shopware\Storefront\ListingPage\ListingPageUrlGenerator;

class ShopDetailQuery extends ShopIdentityQuery
{
    public function __construct(Connection $connection, FieldHelper $fieldHelper, TranslationContext $context)
    {
        parent::__construct($connection, $fieldHelper, $context);

        $this->addSelect($fieldHelper->getCountryFields());
        $this->addSelect($fieldHelper->getPaymentMethodFields());
        $this->addSelect($fieldHelper->getShippingMethodFields());
        $this->addSelect($fieldHelper->getTemplateFields());
        $this->addSelect($fieldHelper->getCustomerGroupFields());
        $this->addSelect($fieldHelper->getCategoryFields());

        //shop system category
        $this->addSelect($fieldHelper->getSeoUrlFields());
        $this->innerJoin('shop', 'category', 'category', 'category.id = shop.category_id');
        $this->leftJoin('category', 'category_attribute', 'categoryAttribute', 'categoryAttribute.category_id = category.id');
        $this->leftJoin('category', 'seo_url', 'seoUrl', 'seoUrl.foreign_key = category.id AND seoUrl.name = :categorySeoUrlName AND is_canonical = 1 AND seoUrl.shop_id = :seoUrlShopId');
        $this->setParameter('seoUrlShopId', $context->getShopId());
        $this->setParameter('categorySeoUrlName', ListingPageUrlGenerator::ROUTE_NAME);

        //default shipping location
        $this->innerJoin('shop', 's_core_countries', 'country', 'country.id = shop.country_id');
        $this->leftJoin('country', 's_core_countries_attributes', 'countryAttribute', 'countryAttribute.countryID = country.id');

        //default payment method
        $this->innerJoin('shop', 's_core_paymentmeans', 'paymentMethod', 'paymentMethod.id = shop.payment_id');
        $this->leftJoin('paymentMethod', 's_core_paymentmeans_attributes', 'paymentMethodAttribute', 'paymentMethodAttribute.paymentmeanID = paymentMethod.id');

        //default shipping method
        $this->innerJoin('shop', 's_premium_dispatch', 'shippingMethod', 'shippingMethod.id = shop.dispatch_id');
        $this->leftJoin('shippingMethod', 's_premium_dispatch_attributes', 'shippingMethodAttribute', 'shippingMethodAttribute.dispatchID = shippingMethod.id');

        //customer groups
        $this->innerJoin('shop', 's_core_customergroups', 'customerGroup', 'customerGroup.id = shop.customer_group_id');
        $this->leftJoin('customerGroup', 's_core_customergroups_attributes', 'customerGroupAttribute', 'customerGroupAttribute.customerGroupID = customerGroup.id');

        //joins which considers sub shop inheritance

        //shop template
        $this->innerJoin('shop', 's_core_templates', 'template', 'main.template_id = template.id');

        $fieldHelper->addCountryTranslation($this, $context);
        $fieldHelper->addPaymentTranslation($this, $context);
        $fieldHelper->addDeliveryTranslation($this, $context);
    }
}