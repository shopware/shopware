<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Workspace;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\UserDefinition;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\ContentSystem\Workspace\ContentWorkspaceTest
 */
#[Package('framework')]
class ContentWorkspaceDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'content_workspace';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ContentWorkspaceEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ContentWorkspaceCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(AdminApiSource::class), new PrimaryKey(), new Required()),
            (new StringField('name', 'name', 255))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new JsonField('configuration', 'configuration'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new CreatedByField([Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE]))->addFlags(new ApiAware(AdminApiSource::class)),
            (new ManyToOneAssociationField('createdBy', 'created_by_id', UserDefinition::class, 'id', false))->addFlags(new ApiAware(AdminApiSource::class)),
        ]);
    }
}
