<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\ExtensionRegistryInterface;
use Shopware\Framework\Read\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\SeoUrl\Extension\SeoUrlExtension;
use Shopware\SeoUrl\Struct\SeoUrlBasicStruct;

class SeoUrlBasicFactory extends Factory
{
    const ROOT_NAME = 'seo_url';
    const EXTENSION_NAMESPACE = 'seoUrl';

    const FIELDS = [
       'uuid' => 'uuid',
       'seoHash' => 'seo_hash',
       'shopUuid' => 'shop_uuid',
       'name' => 'name',
       'foreignKey' => 'foreign_key',
       'pathInfo' => 'path_info',
       'seoPathInfo' => 'seo_path_info',
       'isCanonical' => 'is_canonical',
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
        SeoUrlBasicStruct $seoUrl,
        QuerySelection $selection,
        TranslationContext $context
    ): SeoUrlBasicStruct {
        $seoUrl->setUuid((string) $data[$selection->getField('uuid')]);
        $seoUrl->setSeoHash((string) $data[$selection->getField('seoHash')]);
        $seoUrl->setShopUuid((string) $data[$selection->getField('shopUuid')]);
        $seoUrl->setName((string) $data[$selection->getField('name')]);
        $seoUrl->setForeignKey((string) $data[$selection->getField('foreignKey')]);
        $seoUrl->setPathInfo((string) $data[$selection->getField('pathInfo')]);
        $seoUrl->setSeoPathInfo((string) $data[$selection->getField('seoPathInfo')]);
        $seoUrl->setIsCanonical((bool) $data[$selection->getField('isCanonical')]);
        $seoUrl->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $seoUrl->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);

        /** @var $extension SeoUrlExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($seoUrl, $data, $selection, $context);
        }

        return $seoUrl;
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
            'seo_url_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.seo_url_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
