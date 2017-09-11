<?php declare(strict_types=1);

namespace Shopware\Album\Gateway\Resource;

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

class AlbumResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const POSITION_FIELD = 'position';
    protected const CREATE_THUMBNAILS_FIELD = 'createThumbnails';
    protected const THUMBNAIL_SIZE_FIELD = 'thumbnailSize';
    protected const ICON_FIELD = 'icon';
    protected const THUMBNAIL_HIGH_DPI_FIELD = 'thumbnailHighDpi';
    protected const THUMBNAIL_QUALITY_FIELD = 'thumbnailQuality';
    protected const THUMBNAIL_HIGH_DPI_QUALITY_FIELD = 'thumbnailHighDpiQuality';

    public function __construct()
    {
        parent::__construct('album');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::CREATE_THUMBNAILS_FIELD] = (new IntField('create_thumbnails'))->setFlags(new Required());
        $this->fields[self::THUMBNAIL_SIZE_FIELD] = (new LongTextField('thumbnail_size'))->setFlags(new Required());
        $this->fields[self::ICON_FIELD] = (new StringField('icon'))->setFlags(new Required());
        $this->fields[self::THUMBNAIL_HIGH_DPI_FIELD] = new BoolField('thumbnail_high_dpi');
        $this->fields[self::THUMBNAIL_QUALITY_FIELD] = new IntField('thumbnail_quality');
        $this->fields[self::THUMBNAIL_HIGH_DPI_QUALITY_FIELD] = new IntField('thumbnail_high_dpi_quality');
        $this->fields['parent'] = new ReferenceField('parentUuid', 'uuid', \Shopware\Album\Gateway\Resource\AlbumResource::class);
        $this->fields['parentUuid'] = (new FkField('parent_uuid', \Shopware\Album\Gateway\Resource\AlbumResource::class, 'uuid'));
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Gateway\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = new SubresourceField(\Shopware\Album\Gateway\Resource\AlbumTranslationResource::class, 'languageUuid');
        $this->fields['s'] = new SubresourceField(\Shopware\Album\Gateway\Resource\AlbumResource::class);
        $this->fields['medias'] = new SubresourceField(\Shopware\Media\Gateway\Resource\MediaResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Album\Gateway\Resource\AlbumResource::class,
            \Shopware\Album\Gateway\Resource\AlbumTranslationResource::class,
            \Shopware\Media\Gateway\Resource\MediaResource::class
        ];
    }
}
