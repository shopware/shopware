<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\FlowAction;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal (flag:FEATURE_NEXT_17540) - only for use by the app-system
 *
 * @method void                     add(AppFlowActionEntity $entity)
 * @method void                     set(string $key, AppFlowActionEntity $entity)
 * @method AppFlowActionEntity[]    getIterator()
 * @method AppFlowActionEntity[]    getElements()
 * @method AppFlowActionEntity|null get(string $key)
 * @method AppFlowActionEntity|null first()
 * @method AppFlowActionEntity|null last()
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
