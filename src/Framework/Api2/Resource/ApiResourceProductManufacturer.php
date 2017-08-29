<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Resource;

use Shopware\Framework\Api2\Field\UuidField;

class ApiResourceProductManufacturer extends ApiResource
{
    public function __construct()
    {
        parent::__construct('product_manufacturer');
        $this->primaryKeyFields['uuid'] = new UuidField('uuid');
    }
}