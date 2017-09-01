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

class CoreWidgetViewsResource extends Resource
{
    protected const WIDGET_ID_FIELD = 'widgetId';
    protected const AUTH_ID_FIELD = 'authId';
    protected const COLUMN_FIELD = 'column';
    protected const POSITION_FIELD = 'position';

    public function __construct()
    {
        parent::__construct('s_core_widget_views');
        
        $this->fields[self::WIDGET_ID_FIELD] = (new IntField('widget_id'))->setFlags(new Required());
        $this->fields[self::AUTH_ID_FIELD] = (new IntField('auth_id'))->setFlags(new Required());
        $this->fields[self::COLUMN_FIELD] = (new IntField('column'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CoreWidgetViewsResource::class
        ];
    }
}
