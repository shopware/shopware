<?php declare(strict_types=1);

namespace Shopware\Administration\Notification\Extension;

use Shopware\Administration\Notification\NotificationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\User\UserDefinition;

class UserExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('createdNotifications', NotificationDefinition::class, 'created_by_user_id', 'id')
        );
    }

    public function getDefinitionClass(): string
    {
        return UserDefinition::class;
    }
}
