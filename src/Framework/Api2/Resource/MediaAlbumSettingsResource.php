<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Resource;

use Shopware\Framework\Api2\ApiFlag\Required;
use Shopware\Framework\Api2\Field\FkField;
use Shopware\Framework\Api2\Field\IntField;
use Shopware\Framework\Api2\Field\ReferenceField;
use Shopware\Framework\Api2\Field\StringField;
use Shopware\Framework\Api2\Field\BoolField;
use Shopware\Framework\Api2\Field\DateField;
use Shopware\Framework\Api2\Field\SubresourceField;
use Shopware\Framework\Api2\Field\LongTextField;
use Shopware\Framework\Api2\Field\LongTextWithHtmlField;
use Shopware\Framework\Api2\Field\FloatField;
use Shopware\Framework\Api2\Field\TranslatedField;
use Shopware\Framework\Api2\Field\UuidField;
use Shopware\Framework\Api2\Resource\ApiResource;

class MediaAlbumSettingsResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_media_album_settings');
        
        $this->fields['albumID'] = (new IntField('albumID'))->setFlags(new Required());
        $this->fields['createThumbnails'] = (new IntField('create_thumbnails'))->setFlags(new Required());
        $this->fields['thumbnailSize'] = (new LongTextField('thumbnail_size'))->setFlags(new Required());
        $this->fields['icon'] = (new StringField('icon'))->setFlags(new Required());
        $this->fields['thumbnailHighDpi'] = new IntField('thumbnail_high_dpi');
        $this->fields['thumbnailQuality'] = new IntField('thumbnail_quality');
        $this->fields['thumbnailHighDpiQuality'] = new IntField('thumbnail_high_dpi_quality');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\MediaAlbumSettingsResource::class
        ];
    }
}
