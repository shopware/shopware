<?php declare(strict_types=1);

namespace Shopware\ListingSorting\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\ExtensionRegistryInterface;
use Shopware\Framework\Read\Factory;
use Shopware\ListingSorting\Extension\ListingSortingExtension;
use Shopware\ListingSorting\Struct\ListingSortingBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class ListingSortingBasicFactory extends Factory
{
    const ROOT_NAME = 'listing_sorting';
    const EXTENSION_NAMESPACE = 'listingSorting';

    const FIELDS = [
       'uuid' => 'uuid',
       'active' => 'active',
       'displayInCategories' => 'display_in_categories',
       'position' => 'position',
       'payload' => 'payload',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
       'label' => 'translation.label',
    ];

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry
    ) {
        parent::__construct($connection, $registry);
    }

    public function hydrate(
        array $data,
        ListingSortingBasicStruct $listingSorting,
        QuerySelection $selection,
        TranslationContext $context
    ): ListingSortingBasicStruct {
        $listingSorting->setUuid((string) $data[$selection->getField('uuid')]);
        $listingSorting->setActive((bool) $data[$selection->getField('active')]);
        $listingSorting->setDisplayInCategories((bool) $data[$selection->getField('displayInCategories')]);
        $listingSorting->setPosition((int) $data[$selection->getField('position')]);
        $listingSorting->setPayload((string) $data[$selection->getField('payload')]);
        $listingSorting->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $listingSorting->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
        $listingSorting->setLabel((string) $data[$selection->getField('label')]);

        /** @var $extension ListingSortingExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($listingSorting, $data, $selection, $context);
        }

        return $listingSorting;
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
            'listing_sorting_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.listing_sorting_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
