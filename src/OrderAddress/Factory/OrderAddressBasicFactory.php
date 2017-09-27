<?php

namespace Shopware\OrderAddress\Factory;

use Doctrine\DBAL\Connection;
use Shopware\AreaCountry\Factory\AreaCountryBasicFactory;
use Shopware\AreaCountry\Struct\AreaCountryBasicStruct;
use Shopware\AreaCountryState\Factory\AreaCountryStateBasicFactory;
use Shopware\AreaCountryState\Struct\AreaCountryStateBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\OrderAddress\Extension\OrderAddressExtension;
use Shopware\OrderAddress\Struct\OrderAddressBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class OrderAddressBasicFactory extends Factory
{
    const ROOT_NAME = 'order_address';
    const EXTENSION_NAMESPACE = 'orderAddress';

    const FIELDS = [
       'uuid' => 'uuid',
       'company' => 'company',
       'department' => 'department',
       'salutation' => 'salutation',
       'title' => 'title',
       'first_name' => 'first_name',
       'last_name' => 'last_name',
       'street' => 'street',
       'zipcode' => 'zipcode',
       'city' => 'city',
       'area_country_uuid' => 'area_country_uuid',
       'area_country_state_uuid' => 'area_country_state_uuid',
       'vat_id' => 'vat_id',
       'phone_number' => 'phone_number',
       'additional_address_line1' => 'additional_address_line1',
       'additional_address_line2' => 'additional_address_line2',
       'created_at' => 'created_at',
       'updated_at' => 'updated_at',
    ];

    /**
     * @var AreaCountryBasicFactory
     */
    protected $areaCountryFactory;

    /**
     * @var AreaCountryStateBasicFactory
     */
    protected $areaCountryStateFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        AreaCountryBasicFactory $areaCountryFactory,
        AreaCountryStateBasicFactory $areaCountryStateFactory
    ) {
        parent::__construct($connection, $registry);
        $this->areaCountryFactory = $areaCountryFactory;
        $this->areaCountryStateFactory = $areaCountryStateFactory;
    }

    public function hydrate(
        array $data,
        OrderAddressBasicStruct $orderAddress,
        QuerySelection $selection,
        TranslationContext $context
    ): OrderAddressBasicStruct {
        $orderAddress->setUuid((string) $data[$selection->getField('uuid')]);
        $orderAddress->setCompany(isset($data[$selection->getField('company')]) ? (string) $data[$selection->getField('company')] : null);
        $orderAddress->setDepartment(isset($data[$selection->getField('department')]) ? (string) $data[$selection->getField('department')] : null);
        $orderAddress->setSalutation((string) $data[$selection->getField('salutation')]);
        $orderAddress->setTitle(isset($data[$selection->getField('title')]) ? (string) $data[$selection->getField('title')] : null);
        $orderAddress->setFirstName((string) $data[$selection->getField('first_name')]);
        $orderAddress->setLastName((string) $data[$selection->getField('last_name')]);
        $orderAddress->setStreet((string) $data[$selection->getField('street')]);
        $orderAddress->setZipcode((string) $data[$selection->getField('zipcode')]);
        $orderAddress->setCity((string) $data[$selection->getField('city')]);
        $orderAddress->setAreaCountryUuid((string) $data[$selection->getField('area_country_uuid')]);
        $orderAddress->setAreaCountryStateUuid(isset($data[$selection->getField('area_country_state_uuid')]) ? (string) $data[$selection->getField('area_country_state_uuid')] : null);
        $orderAddress->setVatId(isset($data[$selection->getField('vat_id')]) ? (string) $data[$selection->getField('vat_id')] : null);
        $orderAddress->setPhoneNumber(isset($data[$selection->getField('phone_number')]) ? (string) $data[$selection->getField('phone_number')] : null);
        $orderAddress->setAdditionalAddressLine1(isset($data[$selection->getField('additional_address_line1')]) ? (string) $data[$selection->getField('additional_address_line1')] : null);
        $orderAddress->setAdditionalAddressLine2(isset($data[$selection->getField('additional_address_line2')]) ? (string) $data[$selection->getField('additional_address_line2')] : null);
        $orderAddress->setCreatedAt(isset($data[$selection->getField('created_at')]) ? new \DateTime($data[$selection->getField('created_at')]) : null);
        $orderAddress->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updated_at')]) : null);
        $areaCountry = $selection->filter('country');
        if ($areaCountry && !empty($data[$areaCountry->getField('uuid')])) {
            $orderAddress->setCountry(
                $this->areaCountryFactory->hydrate($data, new AreaCountryBasicStruct(), $areaCountry, $context)
            );
        }
        $areaCountryState = $selection->filter('state');
        if ($areaCountryState && !empty($data[$areaCountryState->getField('uuid')])) {
            $orderAddress->setState(
                $this->areaCountryStateFactory->hydrate($data, new AreaCountryStateBasicStruct(), $areaCountryState, $context)
            );
        }

        /** @var $extension OrderAddressExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($orderAddress, $data, $selection, $context);
        }

        return $orderAddress;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        $fields['country'] = $this->areaCountryFactory->getFields();
        $fields['state'] = $this->areaCountryStateFactory->getFields();

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        if ($areaCountry = $selection->filter('country')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'area_country',
                $areaCountry->getRootEscaped(),
                sprintf('%s.uuid = %s.area_country_uuid', $areaCountry->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->areaCountryFactory->joinDependencies($areaCountry, $query, $context);
        }

        if ($areaCountryState = $selection->filter('state')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'area_country_state',
                $areaCountryState->getRootEscaped(),
                sprintf('%s.uuid = %s.area_country_state_uuid', $areaCountryState->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->areaCountryStateFactory->joinDependencies($areaCountryState, $query, $context);
        }

        if ($translation = $selection->filter('translation')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'order_address_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.order_address_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
        $fields['country'] = $this->areaCountryFactory->getAllFields();
        $fields['state'] = $this->areaCountryStateFactory->getAllFields();

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
}
