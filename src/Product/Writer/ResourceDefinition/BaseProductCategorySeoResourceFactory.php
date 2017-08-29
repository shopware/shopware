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

class BaseProductCategorySeoResourceFactory extends ApiFieldFactory
{
    public function getUuidGeneratorClass(): string
    {
        return RamseyGenerator::class;    
    }

    public function getTableName(): string
    {
        return 'product_category_seo';    
    }
    
    public function getResourceName(): string
    {
        return 'ProductCategorySeo';    
    }

    protected function build(FieldBuilder $builder): FieldBuilder
    {
        return $builder
            ->start()
            ->add('shopId')
            ->setWritable('shop_id')
            ->fromTemplate(IntType::class)
            ->setRequired()
        ->add('shopUuid')
            ->setWritable('shop_uuid')
            ->fromTemplate(TextType::class)
            ->setRequired()
            ->setForeignKey('SCoreShops.uuid')
        ->add('productId')
            ->setWritable('product_id')
            ->fromTemplate(IntType::class)
            ->setRequired()
        ->add('productUuid')
            ->setWritable('product_uuid')
            ->fromTemplate(TextType::class)
            ->setRequired()
            ->setForeignKey('Product.uuid')
        ->add('categoryId')
            ->setWritable('category_id')
            ->fromTemplate(IntType::class)
            ->setRequired()
        ->add('categoryUuid')
            ->setWritable('category_uuid')
            ->fromTemplate(TextType::class)
            ->setRequired()
            ->setForeignKey('Category.uuid')
        ->add('category')
            ->setVirtual('categoryUuid', 'Category')
        ->add('product')
            ->setVirtual('productUuid', 'Product')
        ->add('shop')
            ->setVirtual('shopUuid', 'SCoreShops');
    }
}
