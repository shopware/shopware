<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation;

use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class MailHeaderFooterTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'mail_header_footer_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return MailHeaderFooterTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return MailHeaderFooterTranslationCollection::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return MailHeaderFooterDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->setFlags(new Required()),
            new StringField('description', 'description'),
            new LongTextWithHtmlField('header_html', 'headerHtml'),
            new LongTextField('header_plain', 'headerPlain'),
            new LongTextWithHtmlField('footer_html', 'footerHtml'),
            new LongTextField('footer_plain', 'footerPlain'),
        ]);
    }
}
