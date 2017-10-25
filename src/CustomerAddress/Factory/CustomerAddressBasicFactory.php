<?php declare(strict_types=1);

namespace Shopware\CustomerAddress\Factory;

use Doctrine\DBAL\Connection;
use Shopware\AreaCountry\Factory\AreaCountryBasicFactory;
use Shopware\AreaCountry\Struct\AreaCountryBasicStruct;
use Shopware\AreaCountryState\Factory\AreaCountryStateBasicFactory;
use Shopware\AreaCountryState\Struct\AreaCountryStateBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerAddress\Extension\CustomerAddressExtension;
use Shopware\CustomerAddress\Struct\CustomerAddressBasicStruct;
use Shopware\Framework\Read\ExtensionRegistryInterface;
use Shopware\Framework\Read\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class CustomerAddressBasicFactory extends Factory
{
    const ROOT_NAME = 'customer_address';
    const EXTENSION_NAMESPACE = 'customerAddress';

    const FIELDS = [
       'uuid' => 'uuid',
       'customerUuid' => 'customer_uuid',
       'company' => 'company',
       'department' => 'department',
       'salutation' => 'salutation',
       'title' => 'title',
       'firstName' => 'first_name',
       'lastName' => 'last_name',
       'street' => 'street',
       'zipcode' => 'zipcode',
       'city' => 'city',
       'areaCountryUuid' => 'area_country_uuid',
       'areaCountryStateUuid' => 'area_country_state_uuid',
       'vatId' => 'vat_id',
       'phoneNumber' => 'phone_number',
       'additionalAddressLine1' => 'additional_address_line1',
       'additionalAddressLine2' => 'additional_address_line2',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
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
        CustomerAddressBasicStruct $customerAddress,
        QuerySelection $selection,
        TranslationContext $context
    ): CustomerAddressBasicStruct {
        $customerAddress->setUuid((string) $data[$selection->getField('uuid')]);
        $customerAddress->setCustomerUuid((string) $data[$selection->getField('customerUuid')]);
        $customerAddress->setCompany(isset($data[$selection->getField('company')]) ? (string) $data[$selection->getField('company')] : null);
        $customerAddress->setDepartment(isset($data[$selection->getField('department')]) ? (string) $data[$selection->getField('department')] : null);
        $customerAddress->setSalutation((string) $data[$selection->getField('salutation')]);
        $customerAddress->setTitle(isset($data[$selection->getField('title')]) ? (string) $data[$selection->getField('title')] : null);
        $customerAddress->setFirstName((string) $data[$selection->getField('firstName')]);
        $customerAddress->setLastName((string) $data[$selection->getField('lastName')]);
        $customerAddress->setStreet(isset($data[$selection->getField('street')]) ? (string) $data[$selection->getField('street')] : null);
        $customerAddress->setZipcode((string) $data[$selection->getField('zipcode')]);
        $customerAddress->setCity((string) $data[$selection->getField('city')]);
        $customerAddress->setAreaCountryUuid((string) $data[$selection->getField('areaCountryUuid')]);
        $customerAddress->setAreaCountryStateUuid(isset($data[$selection->getField('areaCountryStateUuid')]) ? (string) $data[$selection->getField('areaCountryStateUuid')] : null);
        $customerAddress->setVatId(isset($data[$selection->getField('vatId')]) ? (string) $data[$selection->getField('vatId')] : null);
        $customerAddress->setPhoneNumber(isset($data[$selection->getField('phoneNumber')]) ? (string) $data[$selection->getField('phoneNumber')] : null);
        $customerAddress->setAdditionalAddressLine1(isset($data[$selection->getField('additionalAddressLine1')]) ? (string) $data[$selection->getField('additionalAddressLine1')] : null);
        $customerAddress->setAdditionalAddressLine2(isset($data[$selection->getField('additionalAddressLine2')]) ? (string) $data[$selection->getField('additionalAddressLine2')] : null);
        $customerAddress->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $customerAddress->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
        $areaCountry = $selection->filter('country');
        if ($areaCountry && !empty($data[$areaCountry->getField('uuid')])) {
            $customerAddress->setCountry(
                $this->areaCountryFactory->hydrate($data, new AreaCountryBasicStruct(), $areaCountry, $context)
            );
        }
        $areaCountryState = $selection->filter('state');
        if ($areaCountryState && !empty($data[$areaCountryState->getField('uuid')])) {
            $customerAddress->setState(
                $this->areaCountryStateFactory->hydrate($data, new AreaCountryStateBasicStruct(), $areaCountryState, $context)
            );
        }

        /** @var $extension CustomerAddressExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($customerAddress, $data, $selection, $context);
        }

        return $customerAddress;
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
        $this->joinCountry($selection, $query, $context);
        $this->joinState($selection, $query, $context);
        $this->joinTranslation($selection, $query, $context);

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

    private function joinState(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($areaCountryState = $selection->filter('state'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'area_country_state',
            $areaCountryState->getRootEscaped(),
            sprintf('%s.uuid = %s.area_country_state_uuid', $areaCountryState->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->areaCountryStateFactory->joinDependencies($areaCountryState, $query, $context);
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
            'customer_address_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.customer_address_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
