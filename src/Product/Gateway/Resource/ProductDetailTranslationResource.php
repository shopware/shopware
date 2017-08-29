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

class ProductDetailTranslationResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('product_detail_translation');
        
        $this->fields['additionalText'] = new StringField('additional_text');
        $this->fields['packUnit'] = new StringField('pack_unit');
        $this->fields['productDetail'] = new ReferenceField('productDetailUuid', 'uuid', \Shopware\Product\Gateway\Resource\ProductDetailResource::class);
        $this->primaryKeyFields['productDetailUuid'] = (new FkField('product_detail_uuid', \Shopware\Product\Gateway\Resource\ProductDetailResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Framework\Api2\Resource\CoreShopsResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Framework\Api2\Resource\CoreShopsResource::class, 'uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Gateway\Resource\ProductDetailResource::class,
            \Shopware\Framework\Api2\Resource\CoreShopsResource::class,
            \Shopware\Product\Gateway\Resource\ProductDetailTranslationResource::class
        ];
    }
}
