<?php

namespace Shopware\PaymentMethod\Factory;

use Doctrine\DBAL\Connection;
use Shopware\AreaCountry\Factory\AreaCountryBasicFactory;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicStruct;
use Shopware\PaymentMethod\Struct\PaymentMethodDetailStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\Shop\Factory\ShopBasicFactory;

class PaymentMethodDetailFactory extends PaymentMethodBasicFactory
{
    /**
     * @var ShopBasicFactory
     */
    protected $shopFactory;

    /**
     * @var AreaCountryBasicFactory
     */
    protected $areaCountryFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        ShopBasicFactory $shopFactory,
        AreaCountryBasicFactory $areaCountryFactory
    ) {
        parent::__construct($connection, $registry);
        $this->shopFactory = $shopFactory;
        $this->areaCountryFactory = $areaCountryFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());
        $fields['_sub_select_shop_uuids'] = '_sub_select_shop_uuids';
        $fields['_sub_select_areaCountry_uuids'] = '_sub_select_areaCountry_uuids';

        return $fields;
    }

    public function hydrate(
        array $data,
        PaymentMethodBasicStruct $paymentMethod,
        QuerySelection $selection,
        TranslationContext $context
    ): PaymentMethodBasicStruct {
        /** @var PaymentMethodDetailStruct $paymentMethod */
        $paymentMethod = parent::hydrate($data, $paymentMethod, $selection, $context);

        if ($selection->hasField('_sub_select_shop_uuids')) {
            $uuids = explode('|', $data[$selection->getField('_sub_select_shop_uuids')]);
            $paymentMethod->setShopUuids(array_filter($uuids));
        }

        if ($selection->hasField('_sub_select_areaCountry_uuids')) {
            $uuids = explode('|', $data[$selection->getField('_sub_select_areaCountry_uuids')]);
            $paymentMethod->setCountryUuids(array_filter($uuids));
        }

        return $paymentMethod;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        if ($shops = $selection->filter('shops')) {
            $mapping = QuerySelection::escape($shops->getRoot() . '.mapping');

            $query->leftJoin(
                $selection->getRootEscaped(),
                'payment_method_shop',
                $mapping,
                sprintf('%s.uuid = %s.payment_method_uuid', $selection->getRootEscaped(), $mapping)
            );
            $query->leftJoin(
                $mapping,
                'shop',
                $shops->getRootEscaped(),
                sprintf('%s.shop_uuid = %s.uuid', $mapping, $shops->getRootEscaped())
            );

            $this->shopFactory->joinDependencies($shops, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }

        if ($selection->hasField('_sub_select_shop_uuids')) {
            $query->addSelect('
                (
                    SELECT GROUP_CONCAT(mapping.shop_uuid SEPARATOR \'|\')
                    FROM payment_method_shop mapping
                    WHERE mapping.payment_method_uuid = ' . $selection->getRootEscaped() . '.uuid
                ) as ' . QuerySelection::escape($selection->getField('_sub_select_shop_uuids'))
            );
        }

        if ($countries = $selection->filter('countries')) {
            $mapping = QuerySelection::escape($countries->getRoot() . '.mapping');

            $query->leftJoin(
                $selection->getRootEscaped(),
                'payment_method_country',
                $mapping,
                sprintf('%s.uuid = %s.payment_method_uuid', $selection->getRootEscaped(), $mapping)
            );
            $query->leftJoin(
                $mapping,
                'area_country',
                $countries->getRootEscaped(),
                sprintf('%s.area_country_uuid = %s.uuid', $mapping, $countries->getRootEscaped())
            );

            $this->areaCountryFactory->joinDependencies($countries, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }

        if ($selection->hasField('_sub_select_areaCountry_uuids')) {
            $query->addSelect('
                (
                    SELECT GROUP_CONCAT(mapping.area_country_uuid SEPARATOR \'|\')
                    FROM payment_method_country mapping
                    WHERE mapping.payment_method_uuid = ' . $selection->getRootEscaped() . '.uuid
                ) as ' . QuerySelection::escape($selection->getField('_sub_select_areaCountry_uuids'))
            );
        }
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['shops'] = $this->shopFactory->getAllFields();
        $fields['countries'] = $this->areaCountryFactory->getAllFields();

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
}
