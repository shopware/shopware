<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\ShippingMethod\Factory;

use Doctrine\DBAL\Connection;
use Shopware\AreaCountry\Factory\AreaCountryBasicFactory;
use Shopware\Category\Factory\CategoryBasicFactory;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Holiday\Factory\HolidayBasicFactory;
use Shopware\PaymentMethod\Factory\PaymentMethodBasicFactory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicStruct;
use Shopware\ShippingMethod\Struct\ShippingMethodDetailStruct;
use Shopware\ShippingMethodPrice\Factory\ShippingMethodPriceBasicFactory;

class ShippingMethodDetailFactory extends ShippingMethodBasicFactory
{
    /**
     * @var CategoryBasicFactory
     */
    protected $categoryFactory;

    /**
     * @var AreaCountryBasicFactory
     */
    protected $areaCountryFactory;

    /**
     * @var HolidayBasicFactory
     */
    protected $holidayFactory;

    /**
     * @var PaymentMethodBasicFactory
     */
    protected $paymentMethodFactory;

    /**
     * @var ShippingMethodPriceBasicFactory
     */
    protected $shippingMethodPriceFactory;

    public function __construct(
        Connection $connection,
        array $extensions,
        CategoryBasicFactory $categoryFactory,
        AreaCountryBasicFactory $areaCountryFactory,
        HolidayBasicFactory $holidayFactory,
        PaymentMethodBasicFactory $paymentMethodFactory,
        ShippingMethodPriceBasicFactory $shippingMethodPriceFactory
    ) {
        parent::__construct($connection, $extensions);
        $this->categoryFactory = $categoryFactory;
        $this->areaCountryFactory = $areaCountryFactory;
        $this->holidayFactory = $holidayFactory;
        $this->paymentMethodFactory = $paymentMethodFactory;
        $this->shippingMethodPriceFactory = $shippingMethodPriceFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());
        $fields['_sub_select_category_uuids'] = '_sub_select_category_uuids';
        $fields['_sub_select_areaCountry_uuids'] = '_sub_select_areaCountry_uuids';
        $fields['_sub_select_holiday_uuids'] = '_sub_select_holiday_uuids';
        $fields['_sub_select_paymentMethod_uuids'] = '_sub_select_paymentMethod_uuids';

        return $fields;
    }

    public function hydrate(
        array $data,
        ShippingMethodBasicStruct $shippingMethod,
        QuerySelection $selection,
        TranslationContext $context
    ): ShippingMethodBasicStruct {
        /** @var ShippingMethodDetailStruct $shippingMethod */
        $shippingMethod = parent::hydrate($data, $shippingMethod, $selection, $context);

        if ($selection->hasField('_sub_select_category_uuids')) {
            $uuids = explode('|', $data[$selection->getField('_sub_select_category_uuids')]);
            $shippingMethod->setCategoryUuids(array_filter($uuids));
        }

        if ($selection->hasField('_sub_select_areaCountry_uuids')) {
            $uuids = explode('|', $data[$selection->getField('_sub_select_areaCountry_uuids')]);
            $shippingMethod->setCountryUuids(array_filter($uuids));
        }

        if ($selection->hasField('_sub_select_holiday_uuids')) {
            $uuids = explode('|', $data[$selection->getField('_sub_select_holiday_uuids')]);
            $shippingMethod->setHolidayUuids(array_filter($uuids));
        }

        if ($selection->hasField('_sub_select_paymentMethod_uuids')) {
            $uuids = explode('|', $data[$selection->getField('_sub_select_paymentMethod_uuids')]);
            $shippingMethod->setPaymentMethodUuids(array_filter($uuids));
        }

        return $shippingMethod;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        if ($categories = $selection->filter('categories')) {
            $mapping = QuerySelection::escape($categories->getRoot() . '.mapping');

            $query->leftJoin(
                $selection->getRootEscaped(),
                'shipping_method_category',
                $mapping,
                sprintf('%s.uuid = %s.shipping_method_uuid', $selection->getRootEscaped(), $mapping)
            );
            $query->leftJoin(
                $mapping,
                'category',
                $categories->getRootEscaped(),
                sprintf('%s.category_uuid = %s.uuid', $mapping, $categories->getRootEscaped())
            );

            $this->categoryFactory->joinDependencies($categories, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }

        if ($selection->hasField('_sub_select_category_uuids')) {
            $query->addSelect('
                (
                    SELECT GROUP_CONCAT(mapping.category_uuid SEPARATOR \'|\')
                    FROM shipping_method_category mapping
                    WHERE mapping.shipping_method_uuid = ' . $selection->getRootEscaped() . '.uuid
                ) as ' . QuerySelection::escape($selection->getField('_sub_select_category_uuids'))
            );
        }

        if ($countries = $selection->filter('countries')) {
            $mapping = QuerySelection::escape($countries->getRoot() . '.mapping');

            $query->leftJoin(
                $selection->getRootEscaped(),
                'shipping_method_country',
                $mapping,
                sprintf('%s.uuid = %s.shipping_method_uuid', $selection->getRootEscaped(), $mapping)
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
                    FROM shipping_method_country mapping
                    WHERE mapping.shipping_method_uuid = ' . $selection->getRootEscaped() . '.uuid
                ) as ' . QuerySelection::escape($selection->getField('_sub_select_areaCountry_uuids'))
            );
        }

        if ($holidaies = $selection->filter('holidaies')) {
            $mapping = QuerySelection::escape($holidaies->getRoot() . '.mapping');

            $query->leftJoin(
                $selection->getRootEscaped(),
                'shipping_method_holiday',
                $mapping,
                sprintf('%s.uuid = %s.shipping_method_uuid', $selection->getRootEscaped(), $mapping)
            );
            $query->leftJoin(
                $mapping,
                'holiday',
                $holidaies->getRootEscaped(),
                sprintf('%s.holiday_uuid = %s.uuid', $mapping, $holidaies->getRootEscaped())
            );

            $this->holidayFactory->joinDependencies($holidaies, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }

        if ($selection->hasField('_sub_select_holiday_uuids')) {
            $query->addSelect('
                (
                    SELECT GROUP_CONCAT(mapping.holiday_uuid SEPARATOR \'|\')
                    FROM shipping_method_holiday mapping
                    WHERE mapping.shipping_method_uuid = ' . $selection->getRootEscaped() . '.uuid
                ) as ' . QuerySelection::escape($selection->getField('_sub_select_holiday_uuids'))
            );
        }

        if ($paymentMethods = $selection->filter('paymentMethods')) {
            $mapping = QuerySelection::escape($paymentMethods->getRoot() . '.mapping');

            $query->leftJoin(
                $selection->getRootEscaped(),
                'shipping_method_payment_method',
                $mapping,
                sprintf('%s.uuid = %s.shipping_method_uuid', $selection->getRootEscaped(), $mapping)
            );
            $query->leftJoin(
                $mapping,
                'payment_method',
                $paymentMethods->getRootEscaped(),
                sprintf('%s.payment_method_uuid = %s.uuid', $mapping, $paymentMethods->getRootEscaped())
            );

            $this->paymentMethodFactory->joinDependencies($paymentMethods, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }

        if ($selection->hasField('_sub_select_paymentMethod_uuids')) {
            $query->addSelect('
                (
                    SELECT GROUP_CONCAT(mapping.payment_method_uuid SEPARATOR \'|\')
                    FROM shipping_method_payment_method mapping
                    WHERE mapping.shipping_method_uuid = ' . $selection->getRootEscaped() . '.uuid
                ) as ' . QuerySelection::escape($selection->getField('_sub_select_paymentMethod_uuids'))
            );
        }

        if ($prices = $selection->filter('prices')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'shipping_method_price',
                $prices->getRootEscaped(),
                sprintf('%s.uuid = %s.shipping_method_uuid', $selection->getRootEscaped(), $prices->getRootEscaped())
            );

            $this->shippingMethodPriceFactory->joinDependencies($prices, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['categories'] = $this->categoryFactory->getAllFields();
        $fields['countries'] = $this->areaCountryFactory->getAllFields();
        $fields['holidaies'] = $this->holidayFactory->getAllFields();
        $fields['paymentMethods'] = $this->paymentMethodFactory->getAllFields();
        $fields['prices'] = $this->shippingMethodPriceFactory->getAllFields();

        return $fields;
    }

    protected function getExtensionFields(): array
    {
        $fields = parent::getExtensionFields();

        foreach ($this->extensions as $extension) {
            $extensionFields = $extension->getDetailFields();
            foreach ($extensionFields as $key => $field) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }
}
