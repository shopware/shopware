<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonCollection;
use Shopware\Core\Framework\App\Aggregate\AppMcpPrompt\AppMcpPromptCollection;
use Shopware\Core\Framework\App\Aggregate\AppMcpResource\AppMcpResourceCollection;
use Shopware\Core\Framework\App\Aggregate\AppMcpTool\AppMcpToolCollection;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodCollection;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionCollection;
use Shopware\Core\Framework\App\Aggregate\AppTranslation\AppTranslationCollection;
use Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockCollection;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionCollection;
use Shopware\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Template\TemplateCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\ScriptCollection;
use Shopware\Core\Framework\Webhook\WebhookCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\Integration\IntegrationEntity;
use Shopware\Core\System\TaxProvider\TaxProviderCollection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppEntity::class)]
class AppEntityTest extends TestCase
{
    public function testScalarAccessorsRoundTrip(): void
    {
        $app = new AppEntity();

        $app->setName('name');
        $app->setPath('path');
        $app->setAuthor('author');
        $app->setCopyright('copyright');
        $app->setLicense('license');
        $app->setPrivacy('privacy');
        $app->setVersion('version');
        $app->setBaseAppUrl('base-app-url');
        $app->setCheckoutGatewayUrl('checkout-gateway-url');
        $app->setContextGatewayUrl('context-gateway-url');
        $app->setInAppPurchasesGatewayUrl('in-app-purchases-gateway-url');
        $app->setModules([['name' => 'module', 'label' => ['en-GB' => 'Module'], 'parent' => 'app', 'position' => 1]]);
        $app->setMainModule(['name' => 'main', 'label' => ['en-GB' => 'Main'], 'parent' => 'app', 'position' => 1]);
        $app->setCookies([['snippet_name' => 'cookie.name']]);
        $app->setAllowedHosts(['example.com']);
        $app->setIconRaw('icon-raw');
        $app->setIcon('icon');
        $app->setLabel('label');
        $app->setDescription('description');
        $app->setIntegrationId('integration-id');
        $app->setAclRoleId('acl-role-id');
        $app->setActive(true);
        $app->setConfigurable(true);
        $app->setPrivacyPolicyExtensions('privacy-policy-extensions');
        $app->setAllowDisable(true);
        $app->setTemplateLoadPriority(7);
        $app->setSourceType('source-type');
        $app->setSourceConfig(['config' => 'config']);
        $app->setSelfManaged(true);
        $app->setRequestedPrivileges(['product:read']);

        static::assertSame('name', $app->getName());
        static::assertSame('path', $app->getPath());
        static::assertSame('author', $app->getAuthor());
        static::assertSame('copyright', $app->getCopyright());
        static::assertSame('license', $app->getLicense());
        static::assertSame('privacy', $app->getPrivacy());
        static::assertSame('version', $app->getVersion());
        static::assertSame('base-app-url', $app->getBaseAppUrl());
        static::assertSame('checkout-gateway-url', $app->getCheckoutGatewayUrl());
        static::assertSame('context-gateway-url', $app->getContextGatewayUrl());
        static::assertSame('in-app-purchases-gateway-url', $app->getInAppPurchasesGatewayUrl());
        static::assertSame([['name' => 'module', 'label' => ['en-GB' => 'Module'], 'parent' => 'app', 'position' => 1]], $app->getModules());
        static::assertSame(['name' => 'main', 'label' => ['en-GB' => 'Main'], 'parent' => 'app', 'position' => 1], $app->getMainModule());
        static::assertSame([['snippet_name' => 'cookie.name']], $app->getCookies());
        static::assertSame(['example.com'], $app->getAllowedHosts());
        static::assertSame('icon-raw', $app->getIconRaw());
        static::assertSame('icon', $app->getIcon());
        static::assertSame('label', $app->getLabel());
        static::assertSame('description', $app->getDescription());
        static::assertSame('integration-id', $app->getIntegrationId());
        static::assertSame('acl-role-id', $app->getAclRoleId());
        static::assertTrue($app->isActive());
        static::assertTrue($app->isConfigurable());
        static::assertSame('privacy-policy-extensions', $app->getPrivacyPolicyExtensions());
        static::assertTrue($app->getAllowDisable());
        static::assertSame(7, $app->getTemplateLoadPriority());
        static::assertSame('source-type', $app->getSourceType());
        static::assertSame(['config' => 'config'], $app->getSourceConfig());
        static::assertTrue($app->isSelfManaged());
        static::assertSame(['product:read'], $app->getRequestedPrivileges());
    }

    public function testAssociationAccessorsRoundTrip(): void
    {
        $app = new AppEntity();

        $translations = new AppTranslationCollection();
        $integration = new IntegrationEntity();
        $aclRole = new AclRoleEntity();
        $customFieldSets = new CustomFieldSetCollection();
        $actionButtons = new ActionButtonCollection();
        $webhooks = new WebhookCollection();
        $templates = new TemplateCollection();
        $scripts = new ScriptCollection();
        $paymentMethods = new AppPaymentMethodCollection();
        $taxProviders = new TaxProviderCollection();
        $scriptConditions = new AppScriptConditionCollection();
        $cmsBlocks = new AppCmsBlockCollection();
        $flowActions = new AppFlowActionCollection();
        $flowEvents = new AppFlowEventCollection();
        $appShippingMethods = new EntityCollection();
        $mcpTools = new AppMcpToolCollection();
        $mcpPrompts = new AppMcpPromptCollection();
        $mcpResources = new AppMcpResourceCollection();

        $app->setTranslations($translations);
        $app->setIntegration($integration);
        $app->setAclRole($aclRole);
        $app->setCustomFieldSets($customFieldSets);
        $app->setActionButtons($actionButtons);
        $app->setWebhooks($webhooks);
        $app->setTemplates($templates);
        $app->setScripts($scripts);
        $app->setPaymentMethods($paymentMethods);
        $app->setTaxProviders($taxProviders);
        $app->setScriptConditions($scriptConditions);
        $app->setCmsBlocks($cmsBlocks);
        $app->setFlowActions($flowActions);
        $app->setFlowEvents($flowEvents);
        $app->setAppShippingMethods($appShippingMethods);
        $app->setMcpTools($mcpTools);
        $app->setMcpPrompts($mcpPrompts);
        $app->setMcpResources($mcpResources);

        static::assertSame($translations, $app->getTranslations());
        static::assertSame($integration, $app->getIntegration());
        static::assertSame($aclRole, $app->getAclRole());
        static::assertSame($customFieldSets, $app->getCustomFieldSets());
        static::assertSame($actionButtons, $app->getActionButtons());
        static::assertSame($webhooks, $app->getWebhooks());
        static::assertSame($templates, $app->getTemplates());
        static::assertSame($scripts, $app->getScripts());
        static::assertSame($paymentMethods, $app->getPaymentMethods());
        static::assertSame($taxProviders, $app->getTaxProviders());
        static::assertSame($scriptConditions, $app->getScriptConditions());
        static::assertSame($cmsBlocks, $app->getCmsBlocks());
        static::assertSame($flowActions, $app->getFlowActions());
        static::assertSame($flowEvents, $app->getFlowEvents());
        static::assertSame($appShippingMethods, $app->getAppShippingMethods());
        static::assertSame($mcpTools, $app->getMcpTools());
        static::assertSame($mcpPrompts, $app->getMcpPrompts());
        static::assertSame($mcpResources, $app->getMcpResources());
    }
}
