<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation;

use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('sales-channel')]
class MailTemplateTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'mail_template_translation';

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

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return MailTemplateDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('sender_name', 'senderName'))->addFlags(new ApiAware()),
            (new LongTextField('description', 'description'))->addFlags(new ApiAware()),
            (new StringField('subject', 'subject'))->addFlags(new Required(), new AllowHtml(false)),
            (new LongTextField('content_html', 'contentHtml'))->addFlags(new Required(), new AllowHtml(false)),
            (new LongTextField('content_plain', 'contentPlain'))->addFlags(new Required(), new AllowHtml(false)),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);
    }
}
