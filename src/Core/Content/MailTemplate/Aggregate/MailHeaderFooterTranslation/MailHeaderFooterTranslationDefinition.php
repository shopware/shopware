<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation;

use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class MailHeaderFooterTranslationDefinition extends EntityTranslationDefinition
{
    public function getEntityName(): string
    {
        return 'mail_header_footer_translation';
    }

    public static function getParentDefinitionClass(): string
    {
        return MailHeaderFooterDefinition::class;
    }

    public static function getEntityClass(): string
    {
        return MailHeaderFooterTranslationEntity::class;
    }

    public static function getCollectionClass(): string
    {
        return MailHeaderFooterTranslationCollection::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('name', 'name'),
            new StringField('description', 'description'),
            new LongTextWithHtmlField('header_html', 'headerHtml'),
            new LongTextField('header_plain', 'headerPlain'),
            new LongTextWithHtmlField('footer_html', 'footerHtml'),
            new LongTextField('footer_plain', 'footerPlain'),
        ]);
    }
}
