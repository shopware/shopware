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

class BaseProductTranslationResourceFactory extends ApiFieldFactory
{
    public function getUuidGeneratorClass(): string
    {
        return RamseyGenerator::class;    
    }

    public function getTableName(): string
    {
        return 'product_translation';    
    }
    
    public function getResourceName(): string
    {
        return 'ProductTranslation';    
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
        ->add('languageUuid')
            ->setWritable('language_uuid')
            ->fromTemplate(TextType::class)
            ->setPrimary()
            ->setRequired()
            ->setForeignKey('SCoreShops.uuid')
        ->add('name')
            ->setWritable('name')
            ->fromTemplate(TextType::class)
            ->setRequired()
        ->add('keywords')
            ->setWritable('keywords')
            ->fromTemplate(TextType::class)
        ->add('description')
            ->setWritable('description')
            ->fromTemplate(LongTextType::class)
        ->add('descriptionLong')
            ->setWritable('description_long')
            ->fromTemplate(LongTextWithHtmlType::class)
        ->add('metaTitle')
            ->setWritable('meta_title')
            ->fromTemplate(TextType::class)
        ->add('language')
            ->setVirtual('languageUuid', 'SCoreShops')
        ->add('product')
            ->setVirtual('productUuid', 'Product');
    }
}
