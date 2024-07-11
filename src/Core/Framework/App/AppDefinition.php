<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonDefinition;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodDefinition;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionDefinition;
use Shopware\Core\Framework\App\Aggregate\AppShippingMethod\AppShippingMethodDefinition;
use Shopware\Core\Framework\App\Aggregate\AppTranslation\AppTranslationDefinition;
use Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockDefinition;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionDefinition;
use Shopware\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventDefinition;
use Shopware\Core\Framework\App\Template\TemplateDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\ScriptDefinition;
use Shopware\Core\Framework\Webhook\WebhookDefinition;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Core\System\Integration\IntegrationDefinition;
use Shopware\Core\System\TaxProvider\TaxProviderDefinition;

/**
 * @internal
 */
#[Package('core')]
class AppDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AppEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AppCollection::class;
    }

    public function getDefaults(): array
    {
        return [
            'active' => false,
            'configurable' => false,
            'allowDisable' => true,
            'modules' => [],
            'cookies' => [],
            'allowedHosts' => [],
            'templateLoadPriority' => 0,
            'sourceType' => 'local',
            'selfManaged' => false,
        ];
    }

    public function since(): ?string
    {
        return '6.3.1.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new StringField('path', 'path', 4096))->addFlags(new Required()),
            new StringField('author', 'author'),
            new StringField('copyright', 'copyright'),
            new StringField('license', 'license'),
            (new BoolField('active', 'active'))->addFlags(new Required()),
            (new BoolField('configurable', 'configurable'))->addFlags(new Required()),
            new StringField('privacy', 'privacy'),
            (new StringField('version', 'version'))->addFlags(new Required()),
            (new BlobField('icon', 'iconRaw'))->removeFlag(ApiAware::class),
            (new StringField('icon', 'icon'))->addFlags(new WriteProtected(), new Runtime()),
            (new StringField('app_secret', 'appSecret'))->removeFlag(ApiAware::class)->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            new ListField('modules', 'modules', JsonField::class),
            new JsonField('main_module', 'mainModule'),
            new ListField('cookies', 'cookies', JsonField::class),
            (new BoolField('allow_disable', 'allowDisable'))->addFlags(new Required()),
            new StringField('base_app_url', 'baseAppUrl', 1024),
            new ListField('allowed_hosts', 'allowedHosts', StringField::class),
            new IntField('template_load_priority', 'templateLoadPriority'),
            new StringField('checkout_gateway_url', 'checkoutGatewayUrl'),
            new StringField('source_type', 'sourceType'),
            new JsonField('source_config', 'sourceConfig'),
            new BoolField('self_managed', 'selfManaged'),

            (new TranslationsAssociationField(AppTranslationDefinition::class, 'app_id'))->addFlags(new Required(), new CascadeDelete()),
            new TranslatedField('label'),
            new TranslatedField('description'),
            new TranslatedField('privacyPolicyExtensions'),
            (new TranslatedField('customFields'))->addFlags(new Since('6.4.1.0')),

            (new FkField('integration_id', 'integrationId', IntegrationDefinition::class))->addFlags(new Required()),
            new OneToOneAssociationField('integration', 'integration_id', 'id', IntegrationDefinition::class),

            (new FkField('acl_role_id', 'aclRoleId', AclRoleDefinition::class))->addFlags(new Required()),
            new OneToOneAssociationField('aclRole', 'acl_role_id', 'id', AclRoleDefinition::class),

            (new OneToManyAssociationField('customFieldSets', CustomFieldSetDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('actionButtons', ActionButtonDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('templates', TemplateDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('scripts', ScriptDefinition::class, 'app_id'))->addFlags(new CascadeDelete())->removeFlag(ApiAware::class),
            (new OneToManyAssociationField('webhooks', WebhookDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('paymentMethods', AppPaymentMethodDefinition::class, 'app_id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('taxProviders', TaxProviderDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('scriptConditions', AppScriptConditionDefinition::class, 'app_id'))->addFlags(new CascadeDelete())->removeFlag(ApiAware::class),
            (new OneToManyAssociationField('cmsBlocks', AppCmsBlockDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('flowActions', AppFlowActionDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('flowEvents', AppFlowEventDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('appShippingMethods', AppShippingMethodDefinition::class, 'app_id'))->addFlags(new SetNullOnDelete()),
        ]);
    }
}
