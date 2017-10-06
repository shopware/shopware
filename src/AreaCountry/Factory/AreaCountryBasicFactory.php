<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Factory;

use Doctrine\DBAL\Connection;
use Shopware\AreaCountry\Extension\AreaCountryExtension;
use Shopware\AreaCountry\Struct\AreaCountryBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class AreaCountryBasicFactory extends Factory
{
    const ROOT_NAME = 'area_country';
    const EXTENSION_NAMESPACE = 'areaCountry';

    const FIELDS = [
       'uuid' => 'uuid',
       'iso' => 'iso',
       'areaUuid' => 'area_uuid',
       'position' => 'position',
       'shippingFree' => 'shipping_free',
       'taxFree' => 'tax_free',
       'taxfreeForVatId' => 'taxfree_for_vat_id',
       'taxfreeVatidChecked' => 'taxfree_vatid_checked',
       'active' => 'active',
       'iso3' => 'iso3',
       'displayStateInRegistration' => 'display_state_in_registration',
       'forceStateInRegistration' => 'force_state_in_registration',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
       'name' => 'translation.name',
    ];

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry
    ) {
        parent::__construct($connection, $registry);
    }

    public function hydrate(
        array $data,
        AreaCountryBasicStruct $areaCountry,
        QuerySelection $selection,
        TranslationContext $context
    ): AreaCountryBasicStruct {
        $areaCountry->setUuid((string) $data[$selection->getField('uuid')]);
        $areaCountry->setIso(isset($data[$selection->getField('iso')]) ? (string) $data[$selection->getField('iso')] : null);
        $areaCountry->setAreaUuid((string) $data[$selection->getField('areaUuid')]);
        $areaCountry->setPosition((int) $data[$selection->getField('position')]);
        $areaCountry->setShippingFree((bool) $data[$selection->getField('shippingFree')]);
        $areaCountry->setTaxFree((bool) $data[$selection->getField('taxFree')]);
        $areaCountry->setTaxfreeForVatId((bool) $data[$selection->getField('taxfreeForVatId')]);
        $areaCountry->setTaxfreeVatidChecked((bool) $data[$selection->getField('taxfreeVatidChecked')]);
        $areaCountry->setActive((bool) $data[$selection->getField('active')]);
        $areaCountry->setIso3(isset($data[$selection->getField('iso3')]) ? (string) $data[$selection->getField('iso3')] : null);
        $areaCountry->setDisplayStateInRegistration((bool) $data[$selection->getField('displayStateInRegistration')]);
        $areaCountry->setForceStateInRegistration((bool) $data[$selection->getField('forceStateInRegistration')]);
        $areaCountry->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $areaCountry->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
        $areaCountry->setName((string) $data[$selection->getField('name')]);

        /** @var $extension AreaCountryExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($areaCountry, $data, $selection, $context);
        }

        return $areaCountry;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        $this->joinTranslation($selection, $query, $context);

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());

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
            'area_country_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.area_country_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
