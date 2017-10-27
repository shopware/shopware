<?php declare(strict_types=1);

namespace Shopware\Tax\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\ExtensionRegistryInterface;
use Shopware\Api\Read\Factory;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Tax\Extension\TaxExtension;
use Shopware\Tax\Struct\TaxBasicStruct;

class TaxBasicFactory extends Factory
{
    const ROOT_NAME = 'tax';
    const EXTENSION_NAMESPACE = 'tax';

    const FIELDS = [
       'id' => 'id',
       'uuid' => 'uuid',
       'rate' => 'tax_rate',
       'name' => 'name',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
    ];

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry
    ) {
        parent::__construct($connection, $registry);
    }

    public function hydrate(
        array $data,
        TaxBasicStruct $tax,
        QuerySelection $selection,
        TranslationContext $context
    ): TaxBasicStruct {
        $tax->setId((int) $data[$selection->getField('id')]);
        $tax->setUuid((string) $data[$selection->getField('uuid')]);
        $tax->setRate((float) $data[$selection->getField('rate')]);
        $tax->setName((string) $data[$selection->getField('name')]);
        $tax->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $tax->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);

        /** @var $extension TaxExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($tax, $data, $selection, $context);
        }

        return $tax;
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
            'tax_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.tax_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
