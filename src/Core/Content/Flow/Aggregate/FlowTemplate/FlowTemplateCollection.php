<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Aggregate\FlowTemplate;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package business-ops
 *
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
