<?php

namespace Shopware\Category\Gateway\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\FieldHelper;
use Shopware\Storefront\ListingPage\ListingPageUrlGenerator;

class CategoryIdentityQuery extends QueryBuilder
{
    public function __construct(Connection $connection, FieldHelper $fieldHelper, TranslationContext $context)
    {
        parent::__construct($connection);

        //category base data
        $this->addSelect($fieldHelper->getCategoryFields());
        $this->from('category', 'category');
        $this->leftJoin('category', 'category_attribute', 'categoryAttribute', 'categoryAttribute.category_id = category.id');

        //fetch canonical seo url for category
        $this->addSelect($fieldHelper->getSeoUrlFields());
        $this->leftJoin('category', 'seo_url', 'seoUrl', 'seoUrl.foreign_key = category.id AND seoUrl.name = :categorySeoUrlName AND is_canonical = 1 AND seoUrl.shop_id = :seoUrlShopId');
        $this->leftJoin('category', 's_core_shops', 'shop', 'shop.category_id = category.id');

        $this->setParameter('seoUrlShopId', $context->getShopId());
        $this->setParameter('categorySeoUrlName', ListingPageUrlGenerator::ROUTE_NAME);
    }
}