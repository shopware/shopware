<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\ExtensionRegistryInterface;
use Shopware\Api\Read\Factory;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\AreaCountryState\Extension\AreaCountryStateExtension;
use Shopware\AreaCountryState\Struct\AreaCountryStateBasicStruct;
use Shopware\Context\Struct\TranslationContext;

class AreaCountryStateBasicFactory extends Factory
{
    const ROOT_NAME = 'area_country_state';
    const EXTENSION_NAMESPACE = 'areaCountryState';

    const FIELDS = [
       'uuid' => 'uuid',
       'areaCountryUuid' => 'area_country_uuid',
       'shortCode' => 'short_code',
       'position' => 'position',
       'active' => 'active',
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
        AreaCountryStateBasicStruct $areaCountryState,
        QuerySelection $selection,
        TranslationContext $context
    ): AreaCountryStateBasicStruct {
        $areaCountryState->setUuid((string) $data[$selection->getField('uuid')]);
        $areaCountryState->setAreaCountryUuid((string) $data[$selection->getField('areaCountryUuid')]);
        $areaCountryState->setShortCode((string) $data[$selection->getField('shortCode')]);
        $areaCountryState->setPosition((int) $data[$selection->getField('position')]);
        $areaCountryState->setActive((bool) $data[$selection->getField('active')]);
        $areaCountryState->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $areaCountryState->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
        $areaCountryState->setName((string) $data[$selection->getField('name')]);

        /** @var $extension AreaCountryStateExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($areaCountryState, $data, $selection, $context);
        }

        return $areaCountryState;
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
            'area_country_state_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.area_country_state_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
