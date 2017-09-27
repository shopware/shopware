<?php

namespace Shopware\Category\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Category\Extension\CategoryExtension;
use Shopware\Category\Struct\CategoryBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\SeoUrl\Factory\SeoUrlBasicFactory;
use Shopware\SeoUrl\Struct\SeoUrlBasicStruct;

class CategoryBasicFactory extends Factory
{
    const ROOT_NAME = 'category';
    const EXTENSION_NAMESPACE = 'category';

    const FIELDS = [
       'uuid' => 'uuid',
       'parent_uuid' => 'parent_uuid',
       'path' => 'path',
       'position' => 'position',
       'level' => 'level',
       'template' => 'template',
       'active' => 'active',
       'is_blog' => 'is_blog',
       'external' => 'external',
       'hide_filter' => 'hide_filter',
       'hide_top' => 'hide_top',
       'media_uuid' => 'media_uuid',
       'product_box_layout' => 'product_box_layout',
       'product_stream_uuid' => 'product_stream_uuid',
       'hide_sortings' => 'hide_sortings',
       'sorting_uuids' => 'sorting_uuids',
       'facet_uuids' => 'facet_uuids',
       'created_at' => 'created_at',
       'updated_at' => 'updated_at',
       'name' => 'translation.name',
       'path_names' => 'translation.path_names',
       'meta_keywords' => 'translation.meta_keywords',
       'meta_title' => 'translation.meta_title',
       'meta_description' => 'translation.meta_description',
       'cms_headline' => 'translation.cms_headline',
       'cms_description' => 'translation.cms_description',
    ];

    /**
     * @var SeoUrlBasicFactory
     */
    protected $seoUrlFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        SeoUrlBasicFactory $seoUrlFactory
    ) {
        parent::__construct($connection, $registry);
        $this->seoUrlFactory = $seoUrlFactory;
    }

    public function hydrate(
        array $data,
        CategoryBasicStruct $category,
        QuerySelection $selection,
        TranslationContext $context
    ): CategoryBasicStruct {
        $category->setUuid((string) $data[$selection->getField('uuid')]);
        $category->setParentUuid(isset($data[$selection->getField('parent_uuid')]) ? (string) $data[$selection->getField('parent_uuid')] : null);
        $category->setPath(array_values(array_filter(explode('|', (string) $data[$selection->getField('path')]))));
        $category->setPosition((int) $data[$selection->getField('position')]);
        $category->setLevel((int) $data[$selection->getField('level')]);
        $category->setTemplate(isset($data[$selection->getField('template')]) ? (string) $data[$selection->getField('template')] : null);
        $category->setActive((bool) $data[$selection->getField('active')]);
        $category->setIsBlog((bool) $data[$selection->getField('is_blog')]);
        $category->setExternal(isset($data[$selection->getField('external')]) ? (string) $data[$selection->getField('external')] : null);
        $category->setHideFilter((bool) $data[$selection->getField('hide_filter')]);
        $category->setHideTop((bool) $data[$selection->getField('hide_top')]);
        $category->setMediaUuid(isset($data[$selection->getField('media_uuid')]) ? (string) $data[$selection->getField('media_uuid')] : null);
        $category->setProductBoxLayout(isset($data[$selection->getField('product_box_layout')]) ? (string) $data[$selection->getField('product_box_layout')] : null);
        $category->setProductStreamUuid(isset($data[$selection->getField('product_stream_uuid')]) ? (string) $data[$selection->getField('product_stream_uuid')] : null);
        $category->setHideSortings((bool) $data[$selection->getField('hide_sortings')]);
        $category->setSortingUuids(isset($data[$selection->getField('sorting_uuids')]) ? (string) $data[$selection->getField('sorting_uuids')] : null);
        $category->setFacetUuids(isset($data[$selection->getField('facet_uuids')]) ? (string) $data[$selection->getField('facet_uuids')] : null);
        $category->setCreatedAt(isset($data[$selection->getField('created_at')]) ? new \DateTime($data[$selection->getField('created_at')]) : null);
        $category->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updated_at')]) : null);
        $category->setName((string) $data[$selection->getField('name')]);
        $category->setPathNames(array_values(array_filter(explode('|', (string) $data[$selection->getField('path_names')]))));
        $category->setMetaKeywords(isset($data[$selection->getField('meta_keywords')]) ? (string) $data[$selection->getField('meta_keywords')] : null);
        $category->setMetaTitle(isset($data[$selection->getField('meta_title')]) ? (string) $data[$selection->getField('meta_title')] : null);
        $category->setMetaDescription(isset($data[$selection->getField('meta_description')]) ? (string) $data[$selection->getField('meta_description')] : null);
        $category->setCmsHeadline(isset($data[$selection->getField('cms_headline')]) ? (string) $data[$selection->getField('cms_headline')] : null);
        $category->setCmsDescription(isset($data[$selection->getField('cms_description')]) ? (string) $data[$selection->getField('cms_description')] : null);
        $seoUrl = $selection->filter('canonicalUrl');
        if ($seoUrl && !empty($data[$seoUrl->getField('uuid')])) {
            $category->setCanonicalUrl(
                $this->seoUrlFactory->hydrate($data, new SeoUrlBasicStruct(), $seoUrl, $context)
            );
        }

        /** @var $extension CategoryExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($category, $data, $selection, $context);
        }

        return $category;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        $fields['canonicalUrl'] = $this->seoUrlFactory->getFields();

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        if ($canonical = $selection->filter('canonicalUrl')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'seo_url',
                $canonical->getRootEscaped(),
                sprintf('%1$s.uuid = %2$s.foreign_key AND %2$s.name = :categorySeoName AND %2$s.is_canonical = 1 AND %2$s.shop_uuid = :shopUuid', $selection->getRootEscaped(), $canonical->getRootEscaped())
            );
            $query->setParameter('categorySeoName', 'listing_page');
            $query->setParameter('shopUuid', $context->getShopUuid());
        }

        if ($translation = $selection->filter('translation')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'category_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.category_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                    $translation->getRootEscaped(),
                    $selection->getRootEscaped(),
                    $translation->getRootEscaped()
                )
            );
            $query->setParameter('languageUuid', $context->getShopUuid());
        }

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());
        $fields['canonicalUrl'] = $this->seoUrlFactory->getAllFields();

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
}
