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

class BaseCategoryResourceFactory extends ApiFieldFactory
{
    public function getUuidGeneratorClass(): string
    {
        return RamseyGenerator::class;    
    }

    public function getTableName(): string
    {
        return 'category';    
    }
    
    public function getResourceName(): string
    {
        return 'Category';    
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
        ->add('parent')
            ->setWritable('parent')
            ->fromTemplate(IntType::class)
        ->add('path')
            ->setWritable('path')
            ->fromTemplate(TextType::class)
        ->add('description')
            ->setWritable('description')
            ->fromTemplate(TextType::class)
            ->setRequired()
        ->add('position')
            ->setWritable('position')
            ->fromTemplate(IntType::class)
        ->add('level')
            ->setWritable('level')
            ->fromTemplate(IntType::class)
            ->setRequired()
        ->add('added')
            ->setWritable('added')
            ->fromTemplate(DateType::class)
            ->setRequired()
        ->add('changedAt')
            ->setWritable('changed_at')
            ->fromTemplate(DateType::class)
            ->setRequired()
        ->add('metaKeywords')
            ->setWritable('meta_keywords')
            ->fromTemplate(LongTextType::class)
        ->add('metaTitle')
            ->setWritable('meta_title')
            ->fromTemplate(TextType::class)
        ->add('metaDescription')
            ->setWritable('meta_description')
            ->fromTemplate(LongTextType::class)
        ->add('cmsHeadline')
            ->setWritable('cms_headline')
            ->fromTemplate(TextType::class)
        ->add('cmsDescription')
            ->setWritable('cms_description')
            ->fromTemplate(LongTextType::class)
        ->add('template')
            ->setWritable('template')
            ->fromTemplate(TextType::class)
        ->add('active')
            ->setWritable('active')
            ->fromTemplate(BooleanType::class)
            ->setRequired()
        ->add('blog')
            ->setWritable('blog')
            ->fromTemplate(IntType::class)
            ->setRequired()
        ->add('external')
            ->setWritable('external')
            ->fromTemplate(TextType::class)
        ->add('hideFilter')
            ->setWritable('hide_filter')
            ->fromTemplate(IntType::class)
            ->setRequired()
        ->add('hideTop')
            ->setWritable('hide_top')
            ->fromTemplate(IntType::class)
            ->setRequired()
        ->add('mediaId')
            ->setWritable('media_id')
            ->fromTemplate(IntType::class)
        ->add('mediaUuid')
            ->setWritable('media_uuid')
            ->fromTemplate(TextType::class)
            ->setRequired()
        ->add('productBoxLayout')
            ->setWritable('product_box_layout')
            ->fromTemplate(TextType::class)
        ->add('streamId')
            ->setWritable('stream_id')
            ->fromTemplate(IntType::class)
            ->setForeignKey('SProductStreams.id')
        ->add('hideSortings')
            ->setWritable('hide_sortings')
            ->fromTemplate(IntType::class)
        ->add('sortingIds')
            ->setWritable('sorting_ids')
            ->fromTemplate(LongTextType::class)
        ->add('facetIds')
            ->setWritable('facet_ids')
            ->fromTemplate(LongTextType::class)
        ->add('stre')
            ->setVirtual('streUuid', 'SProductStreams');
    }
}
