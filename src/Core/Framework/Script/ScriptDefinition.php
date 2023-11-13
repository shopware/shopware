<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script;

use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class ScriptDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'script';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ScriptEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ScriptCollection::class;
    }

    public function since(): ?string
    {
        return '6.4.7.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new LongTextField('script', 'script'))->addFlags(new Required(), new AllowHtml(false)),
            (new StringField('hook', 'hook'))->addFlags(new Required()),
            (new StringField('name', 'name', 1024))->addFlags(new Required()),
            (new BoolField('active', 'active'))->addFlags(new Required()),
            new FkField('app_id', 'appId', AppDefinition::class),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
        ]);
    }
}
