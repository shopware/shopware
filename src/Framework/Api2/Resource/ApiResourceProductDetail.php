<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Resource;

use Shopware\Framework\Api2\ApiFlag\Required;
use Shopware\Framework\Api2\Field\FkField;
use Shopware\Framework\Api2\Field\ReferenceField;
use Shopware\Framework\Api2\Field\SubresourceField;
use Shopware\Framework\Api2\Field\TranslatedField;
use Shopware\Framework\Api2\Field\UuidField;

class ApiResourceProductDetail extends ApiResource
{
    public function __construct()
    {
        parent::__construct('product_detail');
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
        $this->primaryKeyFields['productUuid'] = (new FKField('product_uuid', ApiResourceProduct::class, 'uuid'))->setFlags(new Required());
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', ApiResourceProduct::class);
//        $this->fields['active'] = new BoolField('name', 'name');
        $this->fields['details'] = new SubresourceField(ApiResourceProductDetail::class);
        $this->fields['translations'] = new SubresourceField(ApiResourceProductTranslation::class);
    }
}