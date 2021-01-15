<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage;

use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageSalesChannel\LandingPageSalesChannelDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageTag\LandingPageTagDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Tag\TagDefinition;

/**
 * @internal (flag:FEATURE_NEXT_12032)
 */
class LandingPageDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'landing_page';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LandingPageCollection::class;
    }

    public function getEntityClass(): string
    {
        return LandingPageEntity::class;
    }

    public function since(): ?string
    {
        // May insert correct since-value
        return '6.3.5.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new BoolField('active', 'active'),
            (new TranslatedField('name'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('customFields'),
            new TranslatedField('slotConfig'),
            new TranslatedField('metaTitle'),
            new TranslatedField('metaDescription'),
            new TranslatedField('keywords'),
            (new TranslationsAssociationField(LandingPageTranslationDefinition::class, 'landing_page_id'))->addFlags(new Required()),
            new ManyToManyAssociationField('tags', TagDefinition::class, LandingPageTagDefinition::class, 'landing_page_id', 'tag_id'),
            new FkField('cms_page_id', 'cmsPageId', CmsPageDefinition::class),
            new ManyToOneAssociationField('cmsPage', 'cms_page_id', CmsPageDefinition::class, 'id', false),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, LandingPageSalesChannelDefinition::class, 'landing_page_id', 'sales_channel_id'),
        ]);
    }
}
