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

class ProductConfiguratorSetResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const PUBLIC_FIELD = 'public';
    protected const TYPE_FIELD = 'type';

    public function __construct()
    {
        parent::__construct('product_configurator_set');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::PUBLIC_FIELD] = (new BoolField('public'))->setFlags(new Required());
        $this->fields[self::TYPE_FIELD] = new IntField('type');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductConfiguratorSetResource::class
        ];
    }
}
