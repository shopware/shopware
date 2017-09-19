<?php

namespace Shopware\Area\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Area\Struct\AreaBasicStruct;
use Shopware\Area\Struct\AreaDetailStruct;
use Shopware\AreaCountry\Factory\AreaCountryDetailFactory;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistry;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class AreaDetailFactory extends AreaBasicFactory
{
    /**
     * @var AreaCountryDetailFactory
     */
    protected $areaCountryFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistry $registry,
        AreaCountryDetailFactory $areaCountryFactory
    ) {
        parent::__construct($connection, $registry);
        $this->areaCountryFactory = $areaCountryFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());

        return $fields;
    }

    public function hydrate(
        array $data,
        AreaBasicStruct $area,
        QuerySelection $selection,
        TranslationContext $context
    ): AreaBasicStruct {
        /** @var AreaDetailStruct $area */
        $area = parent::hydrate($data, $area, $selection, $context);

        return $area;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        if ($countries = $selection->filter('countries')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'area_country',
                $countries->getRootEscaped(),
                sprintf('%s.uuid = %s.area_uuid', $selection->getRootEscaped(), $countries->getRootEscaped())
            );

            $this->areaCountryFactory->joinDependencies($countries, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
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
