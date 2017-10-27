<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\ExtensionRegistryInterface;
use Shopware\Api\Read\Factory;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\PriceGroup\Extension\PriceGroupExtension;
use Shopware\PriceGroup\Struct\PriceGroupBasicStruct;

class PriceGroupBasicFactory extends Factory
{
    const ROOT_NAME = 'price_group';
    const EXTENSION_NAMESPACE = 'priceGroup';

    const FIELDS = [
       'uuid' => 'uuid',
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
        PriceGroupBasicStruct $priceGroup,
        QuerySelection $selection,
        TranslationContext $context
    ): PriceGroupBasicStruct {
        $priceGroup->setUuid((string) $data[$selection->getField('uuid')]);
        $priceGroup->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $priceGroup->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
        $priceGroup->setName((string) $data[$selection->getField('name')]);

        /** @var $extension PriceGroupExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($priceGroup, $data, $selection, $context);
        }

        return $priceGroup;
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
            'price_group_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.price_group_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
