<?php declare(strict_types=1);

namespace Shopware\Unit\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\ExtensionRegistryInterface;
use Shopware\Api\Read\Factory;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Unit\Extension\UnitExtension;
use Shopware\Unit\Struct\UnitBasicStruct;

class UnitBasicFactory extends Factory
{
    const ROOT_NAME = 'unit';
    const EXTENSION_NAMESPACE = 'unit';

    const FIELDS = [
       'uuid' => 'uuid',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
       'shortCode' => 'translation.short_code',
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
        UnitBasicStruct $unit,
        QuerySelection $selection,
        TranslationContext $context
    ): UnitBasicStruct {
        $unit->setUuid((string) $data[$selection->getField('uuid')]);
        $unit->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $unit->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
        $unit->setShortCode((string) $data[$selection->getField('shortCode')]);
        $unit->setName((string) $data[$selection->getField('name')]);

        /** @var $extension UnitExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($unit, $data, $selection, $context);
        }

        return $unit;
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
            'unit_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.unit_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
