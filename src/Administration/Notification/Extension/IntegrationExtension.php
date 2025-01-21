<?php declare(strict_types=1);

namespace Shopware\Administration\Notification\Extension;

use Shopware\Administration\Notification\NotificationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Integration\IntegrationDefinition;

/**
 * @deprecated tag:v6.7.0 - will be removed as it's unused
 *
 * @codeCoverageIgnore
 */
#[Package('administration')]
class IntegrationExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'This class will be removed as it is unused');
        $collection->add(
            new OneToManyAssociationField('createdNotifications', NotificationDefinition::class, 'created_by_integration_id', 'id')
        );
    }

    public function getDefinitionClass(): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'This class will be removed as it is unused');

        return IntegrationDefinition::class;
    }

    public function getEntityName(): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'This class will be removed as it is unused');

        return IntegrationDefinition::ENTITY_NAME;
    }
}
