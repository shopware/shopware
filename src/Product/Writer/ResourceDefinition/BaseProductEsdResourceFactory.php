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

class BaseProductEsdResourceFactory extends ApiFieldFactory
{
    public function getUuidGeneratorClass(): string
    {
        return RamseyGenerator::class;    
    }

    public function getTableName(): string
    {
        return 'product_esd';    
    }
    
    public function getResourceName(): string
    {
        return 'ProductEsd';    
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
        ->add('productUuid')
            ->setWritable('product_uuid')
            ->fromTemplate(TextType::class)
            ->setRequired()
            ->setForeignKey('Product.uuid')
        ->add('productDetailId')
            ->setWritable('product_detail_id')
            ->fromTemplate(IntType::class)
        ->add('productDetailUuid')
            ->setWritable('product_detail_uuid')
            ->fromTemplate(TextType::class)
            ->setRequired()
        ->add('file')
            ->setWritable('file')
            ->fromTemplate(TextType::class)
            ->setRequired()
        ->add('serials')
            ->setWritable('serials')
            ->fromTemplate(IntType::class)
        ->add('notification')
            ->setWritable('notification')
            ->fromTemplate(IntType::class)
        ->add('maxDownloads')
            ->setWritable('max_downloads')
            ->fromTemplate(IntType::class)
        ->add('createdAt')
            ->setWritable('created_at')
            ->fromTemplate(DateType::class)
            ->setDefaultOnInsert()
            ->fromTemplate(NowDefaultValueTemplate::class)
            ->setRequired()
        ->add('product')
            ->setVirtual('productUuid', 'Product');
    }
}
