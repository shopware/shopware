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

class ProductStreamsResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_product_streams');
        
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['conditions'] = new LongTextField('conditions');
        $this->fields['type'] = new IntField('type');
        $this->fields['sorting'] = new LongTextField('sorting');
        $this->fields['description'] = new LongTextField('description');
        $this->fields['sortingId'] = new IntField('sorting_id');
        $this->fields['categorys'] = new SubresourceField(\Shopware\Category\Gateway\Resource\CategoryResource::class);
        $this->fields['articless'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductStreamsArticlesResource::class);
        $this->fields['selections'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductStreamsSelectionResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Category\Gateway\Resource\CategoryResource::class,
            \Shopware\Product\Gateway\Resource\ProductStreamsResource::class,
            \Shopware\Product\Gateway\Resource\ProductStreamsArticlesResource::class,
            \Shopware\Product\Gateway\Resource\ProductStreamsSelectionResource::class
        ];
    }
}
