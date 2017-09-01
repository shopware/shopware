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

class EmotionElementValueResource extends Resource
{
    protected const EMOTIONID_FIELD = 'emotionID';
    protected const ELEMENTID_FIELD = 'elementID';
    protected const COMPONENTID_FIELD = 'componentID';
    protected const FIELDID_FIELD = 'fieldID';
    protected const VALUE_FIELD = 'value';

    public function __construct()
    {
        parent::__construct('s_emotion_element_value');
        
        $this->fields[self::EMOTIONID_FIELD] = (new IntField('emotionID'))->setFlags(new Required());
        $this->fields[self::ELEMENTID_FIELD] = (new IntField('elementID'))->setFlags(new Required());
        $this->fields[self::COMPONENTID_FIELD] = (new IntField('componentID'))->setFlags(new Required());
        $this->fields[self::FIELDID_FIELD] = (new IntField('fieldID'))->setFlags(new Required());
        $this->fields[self::VALUE_FIELD] = new LongTextField('value');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\EmotionElementValueResource::class
        ];
    }
}
