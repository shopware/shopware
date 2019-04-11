<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation;

use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class MailTemplateTranslationDefinition extends EntityTranslationDefinition
{
    public function getEntityName(): string
    {
        return 'mail_template_translation';
    }

    public static function getParentDefinitionClass(): string
    {
        return MailTemplateDefinition::class;
    }

    public static function getEntityClass(): string
    {
        return MailTemplateTranslationEntity::class;
    }

    public static function getCollectionClass(): string
    {
        return MailTemplateTranslationCollection::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('sender_name', 'senderName'),
            new LongTextField('description', 'description'),
            new StringField('subject', 'subject'),
            new LongTextWithHtmlField('content_html', 'contentHtml'),
            new LongTextField('content_plain', 'contentPlain'),
        ]);
    }
}
