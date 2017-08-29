<?php declare(strict_types=1);

namespace Shopware\Product\Writer\ResourceDefinition;

use Shopware\Framework\Api\ApiFieldFactory;
use Shopware\Framework\Api\FieldBuilder;
use Shopware\Framework\Api\ApiFieldTemplate\BooleanType;
use Shopware\Framework\Api\ApiFieldTemplate\DateType;
use Shopware\Framework\Api\ApiFieldTemplate\FKType;
use Shopware\Framework\Api\ApiFieldTemplate\LongTextType;
use Shopware\Framework\Api\ApiFieldTemplate\LongTextWithHtmlType;
use Shopware\Framework\Api\ApiFieldTemplate\NowDefaultValueTemplate;
use Shopware\Framework\Api\ApiFieldTemplate\PKType;
use Shopware\Framework\Api\ApiFieldTemplate\TextType;
use Shopware\Framework\Api\ApiFieldTemplate\IntType;
use Shopware\Framework\Api\ApiFieldTemplate\FloatType;
use Shopware\Framework\Api\UuidGenerator\RamseyGenerator;

class BaseProductDetailResourceFactory extends ApiFieldFactory
{
    public function getUuidGeneratorClass(): string
    {
        return RamseyGenerator::class;    
    }

    public function getTableName(): string
    {
        return 'product_detail';    
    }
    
    public function getResourceName(): string
    {
        return 'ProductDetail';    
    }

    protected function build(FieldBuilder $builder): FieldBuilder
    {
        return $builder
            ->start()
            ->add('uuid')
            ->setWritable('uuid')
            ->fromTemplate(TextType::class)
            ->setPrimary()
            ->setRequired()
        ->add('productUuid')
            ->setWritable('product_uuid')
            ->fromTemplate(TextType::class)
            ->setRequired()
            ->setForeignKey('Product.uuid')
        ->add('supplierNumber')
            ->setWritable('supplier_number')
            ->fromTemplate(TextType::class)
        ->add('kind')
            ->setWritable('kind')
            ->fromTemplate(IntType::class)
        ->add('additionalText')
            ->setWritable('additional_text')
            ->fromTemplate(TextType::class)
        ->add('sales')
            ->setWritable('sales')
            ->fromTemplate(IntType::class)
        ->add('active')
            ->setWritable('active')
            ->fromTemplate(BooleanType::class)
        ->add('stock')
            ->setWritable('stock')
            ->fromTemplate(IntType::class)
        ->add('stockmin')
            ->setWritable('stockmin')
            ->fromTemplate(IntType::class)
        ->add('weight')
            ->setWritable('weight')
            ->fromTemplate(FloatType::class)
        ->add('position')
            ->setWritable('position')
            ->fromTemplate(IntType::class)
            ->setRequired()
        ->add('width')
            ->setWritable('width')
            ->fromTemplate(FloatType::class)
        ->add('height')
            ->setWritable('height')
            ->fromTemplate(FloatType::class)
        ->add('length')
            ->setWritable('length')
            ->fromTemplate(FloatType::class)
        ->add('ean')
            ->setWritable('ean')
            ->fromTemplate(TextType::class)
        ->add('unitId')
            ->setWritable('unit_id')
            ->fromTemplate(IntType::class)
        ->add('purchaseSteps')
            ->setWritable('purchase_steps')
            ->fromTemplate(IntType::class)
        ->add('maxPurchase')
            ->setWritable('max_purchase')
            ->fromTemplate(IntType::class)
        ->add('minPurchase')
            ->setWritable('min_purchase')
            ->fromTemplate(IntType::class)
        ->add('purchaseUnit')
            ->setWritable('purchase_unit')
            ->fromTemplate(FloatType::class)
        ->add('referenceUnit')
            ->setWritable('reference_unit')
            ->fromTemplate(FloatType::class)
        ->add('packUnit')
            ->setWritable('pack_unit')
            ->fromTemplate(TextType::class)
        ->add('releaseDate')
            ->setWritable('release_date')
            ->fromTemplate(DateType::class)
        ->add('shippingFree')
            ->setWritable('shipping_free')
            ->fromTemplate(IntType::class)
        ->add('shippingTime')
            ->setWritable('shipping_time')
            ->fromTemplate(TextType::class)
        ->add('purchasePrice')
            ->setWritable('purchase_price')
            ->fromTemplate(FloatType::class)
        ->add('product')
            ->setVirtual('productUuid', 'Product')
        ->add('additionalText')
            ->setRequired()
            ->setTranslatable('product_detail_translation')
        ->add('packUnit')
            ->setTranslatable('product_detail_translation');
    }
}
