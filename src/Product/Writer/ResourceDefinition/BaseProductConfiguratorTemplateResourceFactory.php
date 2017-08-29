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

class BaseProductConfiguratorTemplateResourceFactory extends ApiFieldFactory
{
    public function getUuidGeneratorClass(): string
    {
        return RamseyGenerator::class;    
    }

    public function getTableName(): string
    {
        return 'product_configurator_template';    
    }
    
    public function getResourceName(): string
    {
        return 'ProductConfiguratorTemplate';    
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
        ->add('productId')
            ->setWritable('product_id')
            ->fromTemplate(IntType::class)
        ->add('orderNumber')
            ->setWritable('order_number')
            ->fromTemplate(TextType::class)
            ->setRequired()
        ->add('suppliernumber')
            ->setWritable('suppliernumber')
            ->fromTemplate(TextType::class)
        ->add('additionaltext')
            ->setWritable('additionaltext')
            ->fromTemplate(TextType::class)
        ->add('impressions')
            ->setWritable('impressions')
            ->fromTemplate(IntType::class)
        ->add('sales')
            ->setWritable('sales')
            ->fromTemplate(IntType::class)
        ->add('active')
            ->setWritable('active')
            ->fromTemplate(BooleanType::class)
        ->add('instock')
            ->setWritable('instock')
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
        ->add('purchasesteps')
            ->setWritable('purchasesteps')
            ->fromTemplate(IntType::class)
        ->add('maxpurchase')
            ->setWritable('maxpurchase')
            ->fromTemplate(IntType::class)
        ->add('minpurchase')
            ->setWritable('minpurchase')
            ->fromTemplate(IntType::class)
        ->add('purchaseunit')
            ->setWritable('purchaseunit')
            ->fromTemplate(FloatType::class)
        ->add('referenceunit')
            ->setWritable('referenceunit')
            ->fromTemplate(FloatType::class)
        ->add('packunit')
            ->setWritable('packunit')
            ->fromTemplate(TextType::class)
        ->add('releasedate')
            ->setWritable('releasedate')
            ->fromTemplate(DateType::class)
        ->add('shippingfree')
            ->setWritable('shippingfree')
            ->fromTemplate(IntType::class)
        ->add('shippingtime')
            ->setWritable('shippingtime')
            ->fromTemplate(TextType::class)
        ->add('purchaseprice')
            ->setWritable('purchaseprice')
            ->fromTemplate(FloatType::class);
    }
}
