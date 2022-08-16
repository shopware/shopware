<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\ActionButton;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 *
 * @extends EntityCollection<ActionButtonEntity>
 */
class ActionButtonCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ActionButtonEntity::class;
    }
}
