<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category;

use Shopware\Core\Content\Category\Aggregate\CategoryTag\CategoryTagDefinition;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Tag\TagDefinition;

class CategoryDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'category';

    public const TYPE_PAGE = 'page';

    public const TYPE_LINK = 'link';

    public const TYPE_FOLDER = 'folder';

    public const LINK_TYPE_EXTERNAL = 'external';

    public const LINK_TYPE_CATEGORY = 'category';

    public const LINK_TYPE_PRODUCT = 'product';

    public const LINK_TYPE_LANDING_PAGE = 'landing_page';

    public const PRODUCT_ASSIGNMENT_TYPE_PRODUCT = 'product';

    public const PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM = 'product_stream';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CategoryCollection::class;
    }

    public function getEntityClass(): string
    {
        return CategoryEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'displayNestedProducts' => true,
            'type' => self::TYPE_PAGE,
            'productAssignmentType' => self::PRODUCT_ASSIGNMENT_TYPE_PRODUCT,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return CategoryHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        $collection = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new VersionField())->addFlags(new ApiAware()),

            (new ParentFkField(self::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new ApiAware(), new Required()),

            (new FkField('after_category_id', 'afterCategoryId', self::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(self::class, 'after_category_version_id'))->addFlags(new ApiAware(), new Required()),

            (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new ApiAware()),

            (new BoolField('display_nested_products', 'displayNestedProducts'))->addFlags(new ApiAware(), new Required()),
            (new IntField('auto_increment', 'autoIncrement'))->addFlags(new WriteProtected()),

            (new TranslatedField('breadcrumb'))->addFlags(new ApiAware(), new WriteProtected()),
            (new TreeLevelField('level', 'level'))->addFlags(new ApiAware()),
            (new TreePathField('path', 'path'))->addFlags(new ApiAware()),
            (new ChildCountField())->addFlags(new ApiAware()),

            (new StringField('type', 'type'))->addFlags(new ApiAware(), new Required()),
            (new StringField('product_assignment_type', 'productAssignmentType'))->addFlags(new ApiAware(), new Required()),
            (new BoolField('visible', 'visible'))->addFlags(new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware()),

            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            new TranslatedField('slotConfig'),
            (new TranslatedField('linkType'))->addFlags(new ApiAware()),
            (new TranslatedField('internalLink'))->addFlags(new ApiAware()),
            (new TranslatedField('externalLink'))->addFlags(new ApiAware()),
            (new TranslatedField('linkNewTab'))->addFlags(new ApiAware()),
            (new TranslatedField('description'))->addFlags(new ApiAware()),
            (new TranslatedField('metaTitle'))->addFlags(new ApiAware()),
            (new TranslatedField('metaDescription'))->addFlags(new ApiAware()),
            (new TranslatedField('keywords'))->addFlags(new ApiAware()),

            (new ParentAssociationField(self::class, 'id'))->addFlags(new ApiAware()),
            (new ChildrenAssociationField(self::class))->addFlags(new ApiAware()),

            (new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new TranslationsAssociationField(CategoryTranslationDefinition::class, 'category_id'))->addFlags(new ApiAware(), new Required()),
            (new ManyToManyAssociationField('products', ProductDefinition::class, ProductCategoryDefinition::class, 'category_id', 'product_id'))->addFlags(new CascadeDelete(), new ReverseInherited('categories')),
            (new ManyToManyAssociationField('nestedProducts', ProductDefinition::class, ProductCategoryTreeDefinition::class, 'category_id', 'product_id'))->addFlags(new CascadeDelete(), new WriteProtected()),
            new ManyToManyAssociationField('tags', TagDefinition::class, CategoryTagDefinition::class, 'category_id', 'tag_id'),

            (new FkField('cms_page_id', 'cmsPageId', CmsPageDefinition::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(CmsPageDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new ManyToOneAssociationField('cmsPage', 'cms_page_id', CmsPageDefinition::class, 'id', false))->addFlags(new ApiAware()),
            new FkField('product_stream_id', 'productStreamId', ProductStreamDefinition::class),
            new ManyToOneAssociationField('productStream', 'product_stream_id', ProductStreamDefinition::class, 'id', false),

            // Reverse Associations not available in store-api
            (new OneToManyAssociationField('navigationSalesChannels', SalesChannelDefinition::class, 'navigation_category_id')),
            (new OneToManyAssociationField('footerSalesChannels', SalesChannelDefinition::class, 'footer_category_id')),
            (new OneToManyAssociationField('serviceSalesChannels', SalesChannelDefinition::class, 'service_category_id')),
            (new OneToManyAssociationField('mainCategories', MainCategoryDefinition::class, 'category_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('seoUrls', SeoUrlDefinition::class, 'foreign_key'))->addFlags(new ApiAware()),
        ]);

        return $collection;
    }
}
