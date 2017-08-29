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

class BaseProductPriceResourceFactory extends ApiFieldFactory
{
    public function getUuidGeneratorClass(): string
    {
        return RamseyGenerator::class;    
    }

    public function getTableName(): string
    {
        return 'product_price';    
    }
    
    public function getResourceName(): string
    {
        return 'ProductPrice';    
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
        ->add('pricegroup')
            ->setWritable('pricegroup')
            ->fromTemplate(TextType::class)
            ->setRequired()
        ->add('from')
            ->setWritable('from')
            ->fromTemplate(IntType::class)
        ->add('to')
            ->setWritable('to')
            ->fromTemplate(IntType::class)
        ->add('productId')
            ->setWritable('product_id')
            ->fromTemplate(IntType::class)
        ->add('productDetailUuid')
            ->setWritable('product_detail_uuid')
            ->fromTemplate(TextType::class)
            ->setRequired()
            ->setForeignKey('ProductDetail.uuid')
        ->add('price')
            ->setWritable('price')
            ->fromTemplate(FloatType::class)
        ->add('pseudoprice')
            ->setWritable('pseudoprice')
            ->fromTemplate(FloatType::class)
        ->add('baseprice')
            ->setWritable('baseprice')
            ->fromTemplate(FloatType::class)
        ->add('percent')
            ->setWritable('percent')
            ->fromTemplate(FloatType::class)
        ->add('productDetail')
            ->setVirtual('productDetailUuid', 'ProductDetail');
    }
}
