<?php declare(strict_types=1);

namespace Shopware\Product\Gateway\Resource;

use Shopware\Framework\Api2\ApiFlag\Required;
use Shopware\Framework\Api2\Field\FkField;
use Shopware\Framework\Api2\Field\IntField;
use Shopware\Framework\Api2\Field\ReferenceField;
use Shopware\Framework\Api2\Field\StringField;
use Shopware\Framework\Api2\Field\BoolField;
use Shopware\Framework\Api2\Field\DateField;
use Shopware\Framework\Api2\Field\SubresourceField;
use Shopware\Framework\Api2\Field\LongTextField;
use Shopware\Framework\Api2\Field\LongTextWithHtmlField;
use Shopware\Framework\Api2\Field\FloatField;
use Shopware\Framework\Api2\Field\TranslatedField;
use Shopware\Framework\Api2\Field\UuidField;
use Shopware\Framework\Api2\Resource\ApiResource;

class ProductEsdSerialResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('product_esd_serial');
        
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['esdId'] = new IntField('esd_id');
        $this->fields['serialNumber'] = (new StringField('serial_number'))->setFlags(new Required());
        $this->fields['productEsd'] = new ReferenceField('productEsdUuid', 'uuid', \Shopware\Product\Gateway\Resource\ProductEsdResource::class);
        $this->fields['productEsdUuid'] = (new FkField('product_esd_uuid', \Shopware\Product\Gateway\Resource\ProductEsdResource::class, 'uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Gateway\Resource\ProductEsdResource::class,
            \Shopware\Product\Gateway\Resource\ProductEsdSerialResource::class
        ];
    }
}
