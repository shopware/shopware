<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\CmsBlock;

use Shopware\Core\Framework\App\Aggregate\CmsBlockTranslation\AppCmsBlockTranslationDefinition;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal (flag:FEATURE_NEXT_14408)
 */
class AppCmsBlockDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'app_cms_block';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AppCmsBlockCollection::class;
    }

    public function getEntityClass(): string
    {
        return AppCmsBlockEntity::class;
    }

    public function since(): ?string
    {
        return '6.4.2.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new JsonField('block', 'block'))->addFlags(new Required()),
            (new LongTextField('template', 'template'))->addFlags(new Required(), new AllowHtml(false)),
            (new LongTextField('styles', 'styles'))->addFlags(new Required()),
            new TranslatedField('label'),
            (new TranslationsAssociationField(AppCmsBlockTranslationDefinition::class, 'app_cms_block_id'))->addFlags(new Required()),
            (new FkField('app_id', 'appId', AppDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
        ]);
    }
}
