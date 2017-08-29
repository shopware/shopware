<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Resource;

use Shopware\Framework\Api2\ApiFlag\Required;
use Shopware\Framework\Api2\Field\FkField;
use Shopware\Framework\Api2\Field\StringField;

class ApiResourceProductTranslation extends ApiResource
{
    public function __construct()
    {
        parent::__construct('product_translation');
        $this->primaryKeyFields['productUuid'] = (new FkField('product_uuid', ApiResourceProduct::class, 'uuid'))->setFlags(new Required());
        $this->primaryKeyFields['languageUuid'] = (new FKField('language_uuid', ApiResourceShop::class, 'uuid'))->setFlags(new Required());
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['description'] = new StringField('description');
        $this->fields['descriptionLong'] = new StringField('description_long');
    }
}