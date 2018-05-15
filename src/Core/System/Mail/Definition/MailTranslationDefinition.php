<?php declare(strict_types=1);

namespace Shopware\System\Mail\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Language\Definition\LanguageDefinition;
use Shopware\System\Mail\Collection\MailTranslationBasicCollection;
use Shopware\System\Mail\Collection\MailTranslationDetailCollection;
use Shopware\System\Mail\Event\MailTranslation\MailTranslationDeletedEvent;
use Shopware\System\Mail\Event\MailTranslation\MailTranslationWrittenEvent;
use Shopware\System\Mail\Repository\MailTranslationRepository;
use Shopware\System\Mail\Struct\MailTranslationBasicStruct;
use Shopware\System\Mail\Struct\MailTranslationDetailStruct;

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
