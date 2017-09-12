<?php declare(strict_types=1);

namespace Shopware\Area\Writer\Resource;

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

class AreaTranslationResource extends Resource
{
    protected const ALBUM_UUID_FIELD = 'albumUuid';
    protected const LANGUAGE_UUID_FIELD = 'languageUuid';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('area_translation');
        
        $this->fields[self::ALBUM_UUID_FIELD] = (new StringField('album_uuid'))->setFlags(new Required());
        $this->fields[self::LANGUAGE_UUID_FIELD] = (new StringField('language_uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new StringField('name');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Area\Writer\Resource\AreaTranslationResource::class
        ];
    }
}
