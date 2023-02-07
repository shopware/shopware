<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppScriptCondition;

use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Framework\App\Aggregate\AppScriptConditionTranslation\AppScriptConditionTranslationDefinition;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class AppScriptConditionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app_script_condition';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AppScriptConditionCollection::class;
    }

    public function getEntityClass(): string
    {
        return AppScriptConditionEntity::class;
    }

    public function since(): ?string
    {
        return '6.4.10.3';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return AppDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('identifier', 'identifier'))->addFlags(new Required()),
            new TranslatedField('name'),
            (new BoolField('active', 'active'))->addFlags(new Required()),
            new StringField('group', 'group'),
            (new LongTextField('script', 'script'))->addFlags(new AllowHtml(false)),
            (new BlobField('constraints', 'constraints'))->removeFlag(ApiAware::class)->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            new JsonField('config', 'config'),
            (new FkField('app_id', 'appId', AppDefinition::class))->addFlags(new CascadeDelete(), new Required()),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
            (new OneToManyAssociationField('ruleConditions', RuleConditionDefinition::class, 'script_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new TranslationsAssociationField(AppScriptConditionTranslationDefinition::class, 'app_script_condition_id'))->addFlags(new Required()),
        ]);
    }
}
