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

class BaseProductConfiguratorTemplatePriceResourceFactory extends ApiFieldFactory
{
    public function getUuidGeneratorClass(): string
    {
        return RamseyGenerator::class;    
    }

    public function getTableName(): string
    {
        return 'product_configurator_template_price';    
    }
    
    public function getResourceName(): string
    {
        return 'ProductConfiguratorTemplatePrice';    
    }

    protected function build(FieldBuilder $builder): FieldBuilder
    {
        return $builder
            ->start()
            ->add('templateId')
            ->setWritable('template_id')
            ->fromTemplate(IntType::class)
        ->add('customerGroupKey')
            ->setWritable('customer_group_key')
            ->fromTemplate(TextType::class)
            ->setRequired()
        ->add('from')
            ->setWritable('from')
            ->fromTemplate(IntType::class)
            ->setRequired()
        ->add('to')
            ->setWritable('to')
            ->fromTemplate(TextType::class)
            ->setRequired()
        ->add('price')
            ->setWritable('price')
            ->fromTemplate(FloatType::class)
        ->add('pseudoprice')
            ->setWritable('pseudoprice')
            ->fromTemplate(FloatType::class)
        ->add('percent')
            ->setWritable('percent')
            ->fromTemplate(FloatType::class)
        ->add('uuid')
            ->setWritable('uuid')
            ->fromTemplate(TextType::class)
            ->setPrimary()
            ->setRequired();
    }
}
