<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateSalesChannel\MailTemplateSalesChannelDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation\MailTemplateTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class MailTemplateDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'mail_template';
    }

    public static function getEntityClass(): string
    {
        return MailTemplateEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            (new EmailField('sender_mail', 'senderMail'))->addFlags(new Required()),
            new StringField('mail_type', 'mailType'),
            new BoolField('system_default', 'systemDefault'),

            // translatable fields
            new TranslatedField('senderName'),
            new TranslatedField('description'),
            new TranslatedField('subject'),
            new TranslatedField('contentHtml'),
            new TranslatedField('contentPlain'),

            new CreatedAtField(),
            new UpdatedAtField(),

            (new TranslationsAssociationField(MailTemplateTranslationDefinition::class, 'mail_template_id'))->addFlags(new Required()),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, MailTemplateSalesChannelDefinition::class, false, 'mail_template_id', 'sales_channel_id'),
        ]);
    }
}
