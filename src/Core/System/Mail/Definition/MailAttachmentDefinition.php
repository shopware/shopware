<?php declare(strict_types=1);

namespace Shopware\System\Mail\Definition;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\System\Mail\Collection\MailAttachmentBasicCollection;
use Shopware\System\Mail\Collection\MailAttachmentDetailCollection;
use Shopware\System\Mail\Event\MailAttachment\MailAttachmentDeletedEvent;
use Shopware\System\Mail\Event\MailAttachment\MailAttachmentWrittenEvent;
use Shopware\System\Mail\Repository\MailAttachmentRepository;
use Shopware\System\Mail\Struct\MailAttachmentBasicStruct;
use Shopware\System\Mail\Struct\MailAttachmentDetailStruct;
use Shopware\Content\Media\Definition\MediaDefinition;

class MailAttachmentDefinition extends EntityDefinition
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
        return 'mail_attachment';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FkField('mail_id', 'mailId', MailDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(MailDefinition::class))->setFlags(new Required()),
            (new FkField('media_id', 'mediaId', MediaDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(MediaDefinition::class))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('mail', 'mail_id', MailDefinition::class, false),
            (new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, false))->setFlags(new SearchRanking(1))
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return MailAttachmentRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return MailAttachmentBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return MailAttachmentDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return MailAttachmentWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return MailAttachmentBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return MailAttachmentDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return MailAttachmentDetailCollection::class;
    }
}
