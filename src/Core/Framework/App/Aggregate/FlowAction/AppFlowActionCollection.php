<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\FlowAction;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<AppFlowActionEntity>
 */
#[Package('core')]
class AppFlowActionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'app_flow_action_collection';
    }

    protected function getExpectedClass(): string
    {
        return AppFlowActionEntity::class;
    }
}
