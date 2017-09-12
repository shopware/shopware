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

class ProductConfiguratorDependencyResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const CONFIGURATOR_SET_ID_FIELD = 'configuratorSetId';
    protected const PARENT_ID_FIELD = 'parentId';
    protected const CHILD_ID_FIELD = 'childId';

    public function __construct()
    {
        parent::__construct('product_configurator_dependency');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::CONFIGURATOR_SET_ID_FIELD] = (new IntField('configurator_set_id'))->setFlags(new Required());
        $this->fields[self::PARENT_ID_FIELD] = new IntField('parent_id');
        $this->fields[self::CHILD_ID_FIELD] = new IntField('child_id');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductConfiguratorDependencyResource::class
        ];
    }
}
