<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation;

use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class MailTemplateTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'mail_template_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return MailTemplateTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return MailTemplateTranslationCollection::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return MailTemplateDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('sender_name', 'senderName'),
            new LongTextField('description', 'description'),
            (new StringField('subject', 'subject'))->setFlags(new Required()),
            (new LongTextField('content_html', 'contentHtml'))->setFlags(new Required(), new AllowHtml()),
            (new LongTextField('content_plain', 'contentPlain'))->setFlags(new Required()),

            new CustomFields(),
        ]);
    }
}
