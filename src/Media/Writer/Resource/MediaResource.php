<?php declare(strict_types=1);

namespace Shopware\Media\Writer\Resource;

use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\LongTextWithHtmlField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Resource;

class MediaResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const DESCRIPTION_FIELD = 'description';
    protected const FILE_NAME_FIELD = 'fileName';
    protected const MIME_TYPE_FIELD = 'mimeType';
    protected const FILE_SIZE_FIELD = 'fileSize';
    protected const META_DATA_FIELD = 'metaData';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('media');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = (new LongTextField('description'))->setFlags(new Required());
        $this->fields[self::FILE_NAME_FIELD] = (new StringField('file_name'))->setFlags(new Required());
        $this->fields[self::MIME_TYPE_FIELD] = (new StringField('mime_type'))->setFlags(new Required());
        $this->fields[self::FILE_SIZE_FIELD] = (new IntField('file_size'))->setFlags(new Required());
        $this->fields[self::META_DATA_FIELD] = new LongTextField('meta_data');
        $this->fields[self::CREATED_AT_FIELD] = (new DateField('created_at'))->setFlags(new Required());
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['blogMedias'] = new SubresourceField(\Shopware\Framework\Write\Resource\BlogMediaResource::class);
        $this->fields['categorys'] = new SubresourceField(\Shopware\Category\Writer\Resource\CategoryResource::class);
        $this->fields['filterValues'] = new SubresourceField(\Shopware\Framework\Write\Resource\FilterValueResource::class);
        $this->fields['mailAttachments'] = new SubresourceField(\Shopware\Framework\Write\Resource\MailAttachmentResource::class);
        $this->fields['album'] = new ReferenceField('albumUuid', 'uuid', \Shopware\Album\Writer\Resource\AlbumResource::class);
        $this->fields['albumUuid'] = (new FkField('album_uuid', \Shopware\Album\Writer\Resource\AlbumResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['user'] = new ReferenceField('userUuid', 'uuid', \Shopware\Framework\Write\Resource\UserResource::class);
        $this->fields['userUuid'] = (new FkField('user_uuid', \Shopware\Framework\Write\Resource\UserResource::class, 'uuid'));
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\BlogMediaResource::class,
            \Shopware\Category\Writer\Resource\CategoryResource::class,
            \Shopware\Framework\Write\Resource\FilterValueResource::class,
            \Shopware\Framework\Write\Resource\MailAttachmentResource::class,
            \Shopware\Album\Writer\Resource\AlbumResource::class,
            \Shopware\Framework\Write\Resource\UserResource::class,
            \Shopware\Media\Writer\Resource\MediaResource::class
        ];
    }    
    
    public function getDefaults(string $type): array {
        if($type === self::FOR_UPDATE) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if($type === self::FOR_INSERT) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
