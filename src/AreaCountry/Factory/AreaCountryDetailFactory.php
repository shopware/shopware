<?php

namespace Shopware\AreaCountry\Factory;

use Doctrine\DBAL\Connection;
use Shopware\AreaCountry\Struct\AreaCountryBasicStruct;
use Shopware\AreaCountry\Struct\AreaCountryDetailStruct;
use Shopware\AreaCountryState\Factory\AreaCountryStateBasicFactory;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class AreaCountryDetailFactory extends AreaCountryBasicFactory
{
    /**
     * @var AreaCountryStateBasicFactory
     */
    protected $areaCountryStateFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        AreaCountryStateBasicFactory $areaCountryStateFactory
    ) {
        parent::__construct($connection, $registry);
        $this->areaCountryStateFactory = $areaCountryStateFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());

        return $fields;
    }

    public function hydrate(
        array $data,
        AreaCountryBasicStruct $areaCountry,
        QuerySelection $selection,
        TranslationContext $context
    ): AreaCountryBasicStruct {
        /** @var AreaCountryDetailStruct $areaCountry */
        $areaCountry = parent::hydrate($data, $areaCountry, $selection, $context);

        return $areaCountry;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        if ($states = $selection->filter('states')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'area_country_state',
                $states->getRootEscaped(),
                sprintf('%s.uuid = %s.area_country_uuid', $selection->getRootEscaped(), $states->getRootEscaped())
            );

            $this->areaCountryStateFactory->joinDependencies($states, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['states'] = $this->areaCountryStateFactory->getAllFields();

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
