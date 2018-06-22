<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Aggregate\MailTranslation;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Mail\MailDefinition;

class MailTranslationDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'mail_translation';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('mail_id', 'mailId', MailDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(MailDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('from_mail', 'fromMail'))->setFlags(new Required()),
            (new StringField('from_name', 'fromName'))->setFlags(new Required()),
            (new StringField('subject', 'subject'))->setFlags(new Required()),
            (new LongTextField('content', 'content'))->setFlags(new Required()),
            (new LongTextField('content_html', 'contentHtml'))->setFlags(new Required()),
            new ManyToOneAssociationField('mail', 'mail_id', MailDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return MailTranslationCollection::class;
    }

    public static function getStructClass(): string
    {
        return MailTranslationStruct::class;
    }
}
