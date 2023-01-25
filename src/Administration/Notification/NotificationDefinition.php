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
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Integration\IntegrationDefinition;
use Shopware\Core\System\User\UserDefinition;

#[Package('administration')]
class NotificationDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'notification';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return NotificationCollection::class;
    }

    public function getEntityClass(): string
    {
        return NotificationEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'requiredPrivileges' => [],
            'adminOnly' => false,
        ];
    }

    public function since(): ?string
    {
        return '6.4.7.0';
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([
            new ReadProtection(Context::SYSTEM_SCOPE),
            new WriteProtection(Context::SYSTEM_SCOPE),
        ]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('status', 'status'))->addFlags(new Required()),
            (new StringField('message', 'message'))->addFlags(new Required()),
            new BoolField('admin_only', 'adminOnly'),
            new ListField('required_privileges', 'requiredPrivileges'),

            new FkField('created_by_integration_id', 'createdByIntegrationId', IntegrationDefinition::class),
            new FkField('created_by_user_id', 'createdByUserId', UserDefinition::class),

            new ManyToOneAssociationField('createdByIntegration', 'created_by_integration_id', IntegrationDefinition::class, 'id', false),
            new ManyToOneAssociationField('createdByUser', 'created_by_user_id', UserDefinition::class, 'id', false),
        ]);
    }
}
