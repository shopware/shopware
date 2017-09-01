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

class CoreSubscribesResource extends Resource
{
    protected const SUBSCRIBE_FIELD = 'subscribe';
    protected const TYPE_FIELD = 'type';
    protected const LISTENER_FIELD = 'listener';
    protected const PLUGINID_FIELD = 'pluginID';
    protected const POSITION_FIELD = 'position';

    public function __construct()
    {
        parent::__construct('s_core_subscribes');
        
        $this->fields[self::SUBSCRIBE_FIELD] = (new StringField('subscribe'))->setFlags(new Required());
        $this->fields[self::TYPE_FIELD] = (new IntField('type'))->setFlags(new Required());
        $this->fields[self::LISTENER_FIELD] = (new StringField('listener'))->setFlags(new Required());
        $this->fields[self::PLUGINID_FIELD] = new IntField('pluginID');
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CoreSubscribesResource::class
        ];
    }
}
