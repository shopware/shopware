<?php declare(strict_types=1);

namespace Shopware\Administration\Notification;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\ReadProtection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Integration\IntegrationDefinition;
use Shopware\Core\System\User\UserDefinition;

/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0. Use Shopware\Core\Framework\Notification\NotificationDefinition instead
 */
#[Package('framework')]
class NotificationDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'notification';

    public function getEntityName(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));

        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));

        return NotificationCollection::class;
    }

    public function getEntityClass(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));

        return NotificationEntity::class;
    }

    public function getDefaults(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));

        return [
            'requiredPrivileges' => [],
            'adminOnly' => false,
        ];
    }

    public function since(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));

        return '6.4.7.0';
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));

        return new EntityProtectionCollection([
            new ReadProtection(Context::SYSTEM_SCOPE),
            new WriteProtection(Context::SYSTEM_SCOPE),
        ]);
    }

    protected function defineFields(): FieldCollection
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));

        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('status', 'status'))->addFlags(new Required()),
            (new LongTextField('message', 'message'))->addFlags(new Required()),
            new BoolField('admin_only', 'adminOnly'),
            new ListField('required_privileges', 'requiredPrivileges'),

            new FkField('created_by_integration_id', 'createdByIntegrationId', IntegrationDefinition::class),
            new FkField('created_by_user_id', 'createdByUserId', UserDefinition::class),

            new ManyToOneAssociationField('createdByIntegration', 'created_by_integration_id', IntegrationDefinition::class, 'id', false),
            new ManyToOneAssociationField('createdByUser', 'created_by_user_id', UserDefinition::class, 'id', false),
        ]);
    }
}
