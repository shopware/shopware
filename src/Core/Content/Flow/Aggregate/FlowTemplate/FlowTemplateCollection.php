<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Aggregate\FlowTemplate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<FlowTemplateEntity>
 */
#[Package('business-ops')]
class FlowTemplateCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'flow_template_collection';
    }

    protected function getExpectedClass(): string
    {
        return FlowTemplateEntity::class;
    }
}
