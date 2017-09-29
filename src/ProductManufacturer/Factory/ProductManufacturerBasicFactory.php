<?php declare(strict_types=1);

namespace Shopware\ProductManufacturer\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\ProductManufacturer\Extension\ProductManufacturerExtension;
use Shopware\ProductManufacturer\Struct\ProductManufacturerBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class ProductManufacturerBasicFactory extends Factory
{
    const ROOT_NAME = 'product_manufacturer';
    const EXTENSION_NAMESPACE = 'productManufacturer';

    const FIELDS = [
       'uuid' => 'uuid',
       'link' => 'link',
       'media_uuid' => 'media_uuid',
       'updated_at' => 'updated_at',
       'created_at' => 'created_at',
       'name' => 'translation.name',
       'description' => 'translation.description',
       'meta_title' => 'translation.meta_title',
       'meta_description' => 'translation.meta_description',
       'meta_keywords' => 'translation.meta_keywords',
    ];

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry
    ) {
        parent::__construct($connection, $registry);
    }

    public function hydrate(
        array $data,
        ProductManufacturerBasicStruct $productManufacturer,
        QuerySelection $selection,
        TranslationContext $context
    ): ProductManufacturerBasicStruct {
        $productManufacturer->setUuid((string) $data[$selection->getField('uuid')]);
        $productManufacturer->setLink((string) $data[$selection->getField('link')]);
        $productManufacturer->setMediaUuid(isset($data[$selection->getField('media_uuid')]) ? (string) $data[$selection->getField('media_uuid')] : null);
        $productManufacturer->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updated_at')]) : null);
        $productManufacturer->setCreatedAt(isset($data[$selection->getField('created_at')]) ? new \DateTime($data[$selection->getField('created_at')]) : null);
        $productManufacturer->setName((string) $data[$selection->getField('name')]);
        $productManufacturer->setDescription(isset($data[$selection->getField('description')]) ? (string) $data[$selection->getField('description')] : null);
        $productManufacturer->setMetaTitle(isset($data[$selection->getField('meta_title')]) ? (string) $data[$selection->getField('meta_title')] : null);
        $productManufacturer->setMetaDescription(isset($data[$selection->getField('meta_description')]) ? (string) $data[$selection->getField('meta_description')] : null);
        $productManufacturer->setMetaKeywords(isset($data[$selection->getField('meta_keywords')]) ? (string) $data[$selection->getField('meta_keywords')] : null);

        /** @var $extension ProductManufacturerExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($productManufacturer, $data, $selection, $context);
        }

        return $productManufacturer;
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
            'product_manufacturer_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.product_manufacturer_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
