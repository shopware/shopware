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

class BaseProductConfiguratorPriceVariationResourceFactory extends ApiFieldFactory
{
    public function getUuidGeneratorClass(): string
    {
        return RamseyGenerator::class;    
    }

    public function getTableName(): string
    {
        return 'product_configurator_price_variation';    
    }
    
    public function getResourceName(): string
    {
        return 'ProductConfiguratorPriceVariation';    
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
        ->add('configuratorSetId')
            ->setWritable('configurator_set_id')
            ->fromTemplate(IntType::class)
            ->setRequired()
        ->add('variation')
            ->setWritable('variation')
            ->fromTemplate(FloatType::class)
            ->setRequired()
        ->add('options')
            ->setWritable('options')
            ->fromTemplate(LongTextType::class)
        ->add('isGross')
            ->setWritable('is_gross')
            ->fromTemplate(IntType::class);
    }
}
