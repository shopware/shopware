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

class BaseProductVoteResourceFactory extends ApiFieldFactory
{
    public function getUuidGeneratorClass(): string
    {
        return RamseyGenerator::class;    
    }

    public function getTableName(): string
    {
        return 'product_vote';    
    }
    
    public function getResourceName(): string
    {
        return 'ProductVote';    
    }

    protected function build(FieldBuilder $builder): FieldBuilder
    {
        return $builder
            ->start()
            ->add('productId')
            ->setWritable('product_id')
            ->fromTemplate(IntType::class)
            ->setRequired()
        ->add('name')
            ->setWritable('name')
            ->fromTemplate(TextType::class)
            ->setRequired()
        ->add('headline')
            ->setWritable('headline')
            ->fromTemplate(TextType::class)
            ->setRequired()
        ->add('comment')
            ->setWritable('comment')
            ->fromTemplate(LongTextType::class)
            ->setRequired()
        ->add('points')
            ->setWritable('points')
            ->fromTemplate(FloatType::class)
            ->setRequired()
        ->add('createdAt')
            ->setWritable('created_at')
            ->fromTemplate(DateType::class)
            ->setDefaultOnInsert()
            ->fromTemplate(NowDefaultValueTemplate::class)
        ->add('active')
            ->setWritable('active')
            ->fromTemplate(BooleanType::class)
            ->setRequired()
        ->add('email')
            ->setWritable('email')
            ->fromTemplate(TextType::class)
            ->setRequired()
        ->add('answer')
            ->setWritable('answer')
            ->fromTemplate(LongTextType::class)
            ->setRequired()
        ->add('answerAt')
            ->setWritable('answer_at')
            ->fromTemplate(DateType::class)
        ->add('shopId')
            ->setWritable('shop_id')
            ->fromTemplate(IntType::class)
        ->add('uuid')
            ->setWritable('uuid')
            ->fromTemplate(TextType::class)
            ->setPrimary()
            ->setRequired()
        ->add('productUuid')
            ->setWritable('product_uuid')
            ->fromTemplate(TextType::class)
            ->setRequired()
        ->add('shopUuid')
            ->setWritable('shop_uuid')
            ->fromTemplate(TextType::class)
            ->setRequired();
    }
}
