<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Resource;


use Shopware\Framework\Api2\Field\UuidField;

class ApiResourceShop extends ApiResource
{
    public function __construct(string $resourceName)
    {
        parent::__construct('s_core_shop');
        $this->primaryKeyFields['uuid'] = new UuidField('uuid');
    }
}