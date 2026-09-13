<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SalesChannelCategoryDefinition extends CategoryDefinition implements SalesChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
    }

    public function getEntityClass(): string
    {
        return SalesChannelCategoryEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        $fields->add(
            (new StringField('seo_url', 'seoUrl'))->addFlags(new ApiAware(), new Runtime(['type', 'linkType', 'internalLink']))
        );

        $fields->add(
            (new ObjectField('seoBreadcrumb', 'seoBreadcrumb'))->addFlags(new ApiAware(), new Runtime(['path', 'breadcrumb']), new Since('6.7.15.0'))->setDescription('Breadcrumb of the category, including the seo urls of every category in the path')
        );

        return $fields;
    }
}
