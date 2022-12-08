<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Aggregate\UserConfig;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<UserConfigEntity>
 *
 * @package system-settings
 */
class UserConfigCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'user_config_collection';
    }

    protected function getExpectedClass(): string
    {
        return UserConfigEntity::class;
    }
}
