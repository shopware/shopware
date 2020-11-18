<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType;

use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateSalesChannel\MailTemplateSalesChannelDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation\MailTemplateTypeTranslationDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class MailTemplateTypeDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'mail_template_type';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return MailTemplateTypeEntity::class;
    }

    public function getCollectionClass(): string
    {
        return MailTemplateTypeCollection::class;
    }

    public function getTranslationDefinitionClass(): ?string
    {
        return MailTemplateTypeTranslationDefinition::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new TranslatedField('name'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new StringField('technical_name', 'technicalName'))->addFlags(new Required()),

            new JsonField('available_entities', 'availableEntities'),

            new CreatedAtField(),
            new UpdatedAtField(),

            new TranslatedField('customFields'),

            (new TranslationsAssociationField(MailTemplateTypeTranslationDefinition::class, 'mail_template_type_id'))->addFlags(new Required()),
            (new OneToManyAssociationField('mailTemplates', MailTemplateDefinition::class, 'mail_template_type_id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('salesChannels', MailTemplateSalesChannelDefinition::class, 'mail_template_type_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
