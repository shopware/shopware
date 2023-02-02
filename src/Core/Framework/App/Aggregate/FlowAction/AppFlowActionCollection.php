<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\FlowAction;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AppFlowActionEntity>
 */
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
