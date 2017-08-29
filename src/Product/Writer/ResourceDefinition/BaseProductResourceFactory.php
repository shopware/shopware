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

class BaseProductResourceFactory extends ApiFieldFactory
{
    public function getUuidGeneratorClass(): string
    {
        return RamseyGenerator::class;    
    }

    public function getTableName(): string
    {
        return 'product';    
    }
    
    public function getResourceName(): string
    {
        return 'Product';    
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
        ->add('productManufacturerUuid')
            ->setWritable('product_manufacturer_uuid')
            ->fromTemplate(TextType::class)
            ->setRequired()
            ->setForeignKey('ProductManufacturer.uuid')
        ->add('shippingTime')
            ->setWritable('shipping_time')
            ->fromTemplate(TextType::class)
        ->add('createdAt')
            ->setWritable('created_at')
            ->fromTemplate(DateType::class)
            ->setDefaultOnInsert()
            ->fromTemplate(NowDefaultValueTemplate::class)
        ->add('active')
            ->setWritable('active')
            ->fromTemplate(BooleanType::class)
        ->add('taxUuid')
            ->setWritable('tax_uuid')
            ->fromTemplate(TextType::class)
            ->setRequired()
            ->setForeignKey('SCoreTax.uuid')
        ->add('mainDetailUuid')
            ->setWritable('main_detail_uuid')
            ->fromTemplate(TextType::class)
            ->setForeignKey('ProductDetail.uuid')
        ->add('pseudoSales')
            ->setWritable('pseudo_sales')
            ->fromTemplate(IntType::class)
        ->add('topseller')
            ->setWritable('topseller')
            ->fromTemplate(IntType::class)
        ->add('updatedAt')
            ->setWritable('updated_at')
            ->fromTemplate(DateType::class)
            ->setDefaultOnUpdate()
            ->fromTemplate(NowDefaultValueTemplate::class)
            ->setRequired()
        ->add('priceGroupId')
            ->setWritable('price_group_id')
            ->fromTemplate(IntType::class)
        ->add('filterGroupUuid')
            ->setWritable('filter_group_uuid')
            ->fromTemplate(TextType::class)
        ->add('lastStock')
            ->setWritable('last_stock')
            ->fromTemplate(IntType::class)
            ->setRequired()
        ->add('crossbundlelook')
            ->setWritable('crossbundlelook')
            ->fromTemplate(IntType::class)
            ->setRequired()
        ->add('notification')
            ->setWritable('notification')
            ->fromTemplate(IntType::class)
            ->setRequired()
        ->add('template')
            ->setWritable('template')
            ->fromTemplate(TextType::class)
            ->setRequired()
        ->add('mode')
            ->setWritable('mode')
            ->fromTemplate(IntType::class)
            ->setRequired()
        ->add('availableFrom')
            ->setWritable('available_from')
            ->fromTemplate(DateType::class)
        ->add('availableTo')
            ->setWritable('available_to')
            ->fromTemplate(DateType::class)
        ->add('configuratorSetId')
            ->setWritable('configurator_set_id')
            ->fromTemplate(IntType::class)
        ->add('mainDetail')
            ->setVirtual('mainDetailUuid', 'ProductDetail')
        ->add('productManufacturer')
            ->setVirtual('productManufacturerUuid', 'ProductManufacturer')
        ->add('tax')
            ->setVirtual('taxUuid', 'SCoreTax')
        ->add('name')
            ->setRequired()
            ->setTranslatable('product_translation')
        ->add('keywords')
            ->setTranslatable('product_translation')
        ->add('description')
            ->setTranslatable('product_translation')
        ->add('descriptionLong')
            ->setTranslatable('product_translation')
        ->add('metaTitle')
            ->setTranslatable('product_translation');
    }
}
