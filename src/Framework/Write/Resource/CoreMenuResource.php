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

class CoreMenuResource extends Resource
{
    protected const PARENT_FIELD = 'parent';
    protected const NAME_FIELD = 'name';
    protected const ONCLICK_FIELD = 'onclick';
    protected const CLASS_FIELD = 'class';
    protected const POSITION_FIELD = 'position';
    protected const ACTIVE_FIELD = 'active';
    protected const PLUGINID_FIELD = 'pluginID';
    protected const CONTROLLER_FIELD = 'controller';
    protected const SHORTCUT_FIELD = 'shortcut';
    protected const ACTION_FIELD = 'action';

    public function __construct()
    {
        parent::__construct('s_core_menu');
        
        $this->fields[self::PARENT_FIELD] = new IntField('parent');
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::ONCLICK_FIELD] = new StringField('onclick');
        $this->fields[self::CLASS_FIELD] = new StringField('class');
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::PLUGINID_FIELD] = new IntField('pluginID');
        $this->fields[self::CONTROLLER_FIELD] = new StringField('controller');
        $this->fields[self::SHORTCUT_FIELD] = new StringField('shortcut');
        $this->fields[self::ACTION_FIELD] = new StringField('action');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CoreMenuResource::class
        ];
    }
}
