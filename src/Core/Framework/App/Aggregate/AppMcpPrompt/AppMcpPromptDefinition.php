<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppMcpPrompt;

use Shopware\Core\Framework\App\Aggregate\AppMcpPromptTranslation\AppMcpPromptTranslationDefinition;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppMcpPromptDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app_mcp_prompt';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AppMcpPromptCollection::class;
    }

    public function getEntityClass(): string
    {
        return AppMcpPromptEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.11.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return AppDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new StringField('url', 'url', 2048))->addFlags(new Required()),
            (new FkField('app_id', 'appId', AppDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
            new TranslatedField('label'),
            new TranslatedField('description'),
            (new TranslationsAssociationField(AppMcpPromptTranslationDefinition::class, 'app_mcp_prompt_id'))->addFlags(new Required(), new CascadeDelete()),
        ]);
    }
}
