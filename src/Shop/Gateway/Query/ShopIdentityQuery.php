<?php

namespace Shopware\Shop\Gateway\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\FieldHelper;

class ShopIdentityQuery extends QueryBuilder
{
    public function __construct(Connection $connection, FieldHelper $fieldHelper, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->addSelect($fieldHelper->getShopFields());
        $this->addSelect($fieldHelper->getCurrencyFields());
        $this->addSelect($fieldHelper->getLocaleFields());
        $this->from('s_core_shops', 'shop');
        $this->innerJoin('shop', 's_core_currencies', 'currency', 'currency.id = shop.currency_id');
        $this->innerJoin('shop', 's_core_locales', 'locale', 'locale.id = shop.locale_id');
        $this->innerJoin('shop', 's_core_shops', 'main', 'IFNULL(shop.main_id, shop.id) = main.id');
    }
}