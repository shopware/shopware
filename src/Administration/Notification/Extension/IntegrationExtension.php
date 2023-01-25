<?php declare(strict_types=1);

namespace Shopware\Administration\Notification\Extension;

use Shopware\Administration\Notification\NotificationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Integration\IntegrationDefinition;

#[Package('administration')]
class IntegrationExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('createdNotifications', NotificationDefinition::class, 'created_by_integration_id', 'id')
        );
    }

    public function getDefinitionClass(): string
    {
        return IntegrationDefinition::class;
    }
}
