<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateSalesChannel\MailTemplateSalesChannelDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation\MailTemplateTranslationDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class MailTemplateDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'mail_template';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return MailTemplateEntity::class;
    }

    public function getCollectionClass(): string
    {
        return MailTemplateCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            (new FkField('mail_template_type_id', 'mailTemplateTypeId', MailTemplateTypeDefinition::class))->setFlags(new Required()),
            new BoolField('system_default', 'systemDefault'),

            // translatable fields
            new TranslatedField('senderName'),
            (new TranslatedField('description'))->setFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('subject'))->setFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('contentHtml'),
            new TranslatedField('contentPlain'),
            new TranslatedField('customFields'),

            (new TranslationsAssociationField(MailTemplateTranslationDefinition::class, 'mail_template_id'))
                ->addFlags(new Required()),
            (new OneToManyAssociationField('salesChannels', MailTemplateSalesChannelDefinition::class, 'mail_template_id', 'id'))
                ->addFlags(new CascadeDelete()),
            (new ManyToOneAssociationField('mailTemplateType', 'mail_template_type_id', MailTemplateTypeDefinition::class, 'id'))
                ->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new OneToManyAssociationField('media', MailTemplateMediaDefinition::class, 'mail_template_id', 'id'))
                ->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('salesChannels', MailTemplateSalesChannelDefinition::class, 'mail_template_id', 'id'))
                ->addFlags(new CascadeDelete(), new Deprecated('v3', 'v4', 'event_action entity')),
        ]);

        return $fields;
    }
}
