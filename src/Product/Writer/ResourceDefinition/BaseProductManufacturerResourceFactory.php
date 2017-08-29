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

class BaseProductManufacturerResourceFactory extends ApiFieldFactory
{
    public function getUuidGeneratorClass(): string
    {
        return RamseyGenerator::class;    
    }

    public function getTableName(): string
    {
        return 'product_manufacturer';    
    }
    
    public function getResourceName(): string
    {
        return 'ProductManufacturer';    
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
        ->add('name')
            ->setWritable('name')
            ->fromTemplate(TextType::class)
            ->setRequired()
        ->add('img')
            ->setWritable('img')
            ->fromTemplate(TextType::class)
            ->setRequired()
        ->add('link')
            ->setWritable('link')
            ->fromTemplate(TextType::class)
            ->setRequired()
        ->add('description')
            ->setWritable('description')
            ->fromTemplate(LongTextType::class)
        ->add('metaTitle')
            ->setWritable('meta_title')
            ->fromTemplate(TextType::class)
        ->add('metaDescription')
            ->setWritable('meta_description')
            ->fromTemplate(TextType::class)
        ->add('metaKeywords')
            ->setWritable('meta_keywords')
            ->fromTemplate(TextType::class)
        ->add('updatedAt')
            ->setWritable('updated_at')
            ->fromTemplate(DateType::class)
            ->setDefaultOnUpdate()
            ->fromTemplate(NowDefaultValueTemplate::class)
            ->setRequired();
    }
}
