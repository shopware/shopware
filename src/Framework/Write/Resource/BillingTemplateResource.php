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

class BillingTemplateResource extends Resource
{
    protected const ID_FIELD = 'iD';
    protected const NAME_FIELD = 'name';
    protected const VALUE_FIELD = 'value';
    protected const TYP_FIELD = 'typ';
    protected const GROUP_FIELD = 'group';
    protected const DESC_FIELD = 'desc';
    protected const POSITION_FIELD = 'position';
    protected const SHOW_FIELD = 'show';

    public function __construct()
    {
        parent::__construct('s_billing_template');
        
        $this->primaryKeyFields[self::ID_FIELD] = (new IntField('ID'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::VALUE_FIELD] = (new LongTextField('value'))->setFlags(new Required());
        $this->fields[self::TYP_FIELD] = (new IntField('typ'))->setFlags(new Required());
        $this->fields[self::GROUP_FIELD] = (new StringField('group'))->setFlags(new Required());
        $this->fields[self::DESC_FIELD] = (new StringField('desc'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::SHOW_FIELD] = new IntField('show');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\BillingTemplateResource::class
        ];
    }
}
