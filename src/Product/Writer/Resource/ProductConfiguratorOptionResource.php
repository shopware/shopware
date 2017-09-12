<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

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

class ProductConfiguratorOptionResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const GROUP_ID_FIELD = 'groupId';
    protected const PRODUCT_CONFIGURATOR_GROUP_UUID_FIELD = 'productConfiguratorGroupUuid';
    protected const NAME_FIELD = 'name';
    protected const POSITION_FIELD = 'position';

    public function __construct()
    {
        parent::__construct('product_configurator_option');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::GROUP_ID_FIELD] = new IntField('group_id');
        $this->fields[self::PRODUCT_CONFIGURATOR_GROUP_UUID_FIELD] = (new StringField('product_configurator_group_uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductConfiguratorOptionResource::class
        ];
    }
}
