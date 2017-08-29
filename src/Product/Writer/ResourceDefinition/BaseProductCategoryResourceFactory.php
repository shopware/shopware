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

class BaseProductCategoryResourceFactory extends ApiFieldFactory
{
    public function getUuidGeneratorClass(): string
    {
        return RamseyGenerator::class;    
    }

    public function getTableName(): string
    {
        return 'product_category';    
    }
    
    public function getResourceName(): string
    {
        return 'ProductCategory';    
    }

    protected function build(FieldBuilder $builder): FieldBuilder
    {
        return $builder
            ->start()
            ->add('productUuid')
            ->setWritable('product_uuid')
            ->fromTemplate(TextType::class)
            ->setPrimary()
            ->setRequired()
            ->setForeignKey('Product.uuid')
        ->add('categoryUuid')
            ->setWritable('category_uuid')
            ->fromTemplate(TextType::class)
            ->setPrimary()
            ->setRequired()
            ->setForeignKey('Category.uuid')
        ->add('category')
            ->setVirtual('categoryUuid', 'Category')
        ->add('product')
            ->setVirtual('productUuid', 'Product');
    }
}
