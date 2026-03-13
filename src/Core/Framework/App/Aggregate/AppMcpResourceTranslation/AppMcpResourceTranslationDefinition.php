<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppMcpResourceTranslation;

use Shopware\Core\Framework\App\Aggregate\AppMcpResource\AppMcpResourceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppMcpResourceTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'app_mcp_resource_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AppMcpResourceTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AppMcpResourceTranslationCollection::class;
    }

    public function since(): ?string
    {
        return '6.7.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return AppMcpResourceDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('label', 'label'))->addFlags(new Required()),
            new LongTextField('description', 'description'),
        ]);
    }
}
