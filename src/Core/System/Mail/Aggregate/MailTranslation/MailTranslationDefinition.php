<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Aggregate\MailTranslation;

use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Mail\Aggregate\MailTranslation\Collection\MailTranslationBasicCollection;
use Shopware\Core\System\Mail\Aggregate\MailTranslation\Collection\MailTranslationDetailCollection;
use Shopware\Core\System\Mail\Aggregate\MailTranslation\Event\MailTranslationDeletedEvent;
use Shopware\Core\System\Mail\Aggregate\MailTranslation\Event\MailTranslationWrittenEvent;
use Shopware\Core\System\Mail\Aggregate\MailTranslation\Struct\MailTranslationBasicStruct;
use Shopware\Core\System\Mail\Aggregate\MailTranslation\Struct\MailTranslationDetailStruct;
use Shopware\Core\System\Mail\MailDefinition;

class MailTranslationDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function getEntityName(): string
    {
        return 'mail_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
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

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return MailTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return MailTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return MailTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return MailTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return MailTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return MailTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return MailTranslationDetailCollection::class;
    }
}
