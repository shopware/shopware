<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

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

class CoreTranslationsResource extends Resource
{
    protected const OBJECTTYPE_FIELD = 'objecttype';
    protected const OBJECTDATA_FIELD = 'objectdata';
    protected const OBJECTKEY_FIELD = 'objectkey';
    protected const OBJECTLANGUAGE_FIELD = 'objectlanguage';
    protected const DIRTY_FIELD = 'dirty';

    public function __construct()
    {
        parent::__construct('s_core_translations');
        
        $this->fields[self::OBJECTTYPE_FIELD] = (new StringField('objecttype'))->setFlags(new Required());
        $this->fields[self::OBJECTDATA_FIELD] = (new LongTextField('objectdata'))->setFlags(new Required());
        $this->fields[self::OBJECTKEY_FIELD] = (new IntField('objectkey'))->setFlags(new Required());
        $this->fields[self::OBJECTLANGUAGE_FIELD] = (new StringField('objectlanguage'))->setFlags(new Required());
        $this->fields[self::DIRTY_FIELD] = new IntField('dirty');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CoreTranslationsResource::class
        ];
    }
}
