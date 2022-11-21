<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Lifecycle;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\File\FileLoader;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonEntity;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionCollection;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionEntity;
use Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockEntity;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Event\Hooks\AppDeletedHook;
use Shopware\Core\Framework\App\Event\Hooks\AppInstalledHook;
use Shopware\Core\Framework\App\Event\Hooks\AppUpdatedHook;
use Shopware\Core\Framework\App\Exception\AppAlreadyInstalledException;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Exception\InvalidAppConfigurationException;
use Shopware\Core\Framework\App\FlowAction\FlowAction;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\Persister\FlowActionPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\PermissionPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Permissions;
use Shopware\Core\Framework\App\Template\TemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\Execution\ScriptLoader;
use Shopware\Core\Framework\Script\ScriptEntity;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\WebhookCollection;
use Shopware\Core\Framework\Webhook\WebhookEntity;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationEntity;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use function preg_replace;

/**
 * @internal
 */
class AppLifecycleTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private AppLifecycle $appLifecycle;

    private EntityRepository $appRepository;

    private Context $context;

    private EntityRepository $actionButtonRepository;

    private EventDispatcherInterface $eventDispatcher;

    private Connection $connection;

    public function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->actionButtonRepository = $this->getContainer()->get('app_action_button.repository');

        $this->appLifecycle = $this->getContainer()->get(AppLifecycle::class);

        $userRepository = $this->getContainer()->get('user.repository');
        $userId = $userRepository->searchIds(new Criteria(), Context::createDefaultContext())->firstId();
        $source = new AdminApiSource($userId);
        $source->setIsAdmin(true);
        $this->context = Context::createDefaultContext($source);

        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $cache = $this->getContainer()->get('cache.object');
        $item = $cache->getItem(ScriptLoader::CACHE_KEY);
        $cache->save(CacheCompressor::compress($item, []));

        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testInstall(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $eventWasReceived = false;
        $appId = null;
        $onAppInstalled = function (AppInstalledEvent $event) use (&$eventWasReceived, &$appId, $manifest): void {
            $eventWasReceived = true;
            $appId = $event->getApp()->getId();
            static::assertEquals($manifest, $event->getManifest());
        };
        $this->eventDispatcher->addListener(AppInstalledEvent::class, $onAppInstalled);

        $this->appLifecycle->install($manifest, true, $this->context);

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(AppInstalledHook::HOOK_NAME, $traces);
        static::assertEquals('installed', $traces[AppInstalledHook::HOOK_NAME][0]['output'][0]);

        static::assertTrue($eventWasReceived);
        $this->eventDispatcher->removeListener(AppInstalledEvent::class, $onAppInstalled);
        $criteria = new Criteria();
        $criteria->addAssociation('integration');
        /** @var AppCollection $apps */
        $apps = $this->appRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(1, $apps);
        $appEntity = $apps->first();
        static::assertNotNull($appEntity);
        static::assertEquals('test', $appEntity->getName());
        static::assertEquals(
            base64_encode((string) file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/icon.png')),
            $appEntity->getIcon()
        );
        // assert formatting with \n and \t is preserved
        static::assertEquals(
            'Following personal information will be processed on shopware AG\'s servers:

- Name
- Billing address
- Order value',
            $appEntity->getPrivacyPolicyExtensions()
        );

        static::assertEquals($appId, $appEntity->getId());
        static::assertFalse($appEntity->isConfigurable());
        static::assertTrue($appEntity->getAllowDisable());
        $integrationEntity = $appEntity->getIntegration();
        static::assertNotNull($integrationEntity);
        static::assertFalse($integrationEntity->getAdmin());
        static::assertSame(100, $appEntity->getTemplateLoadPriority());
        static::assertEquals('https://base-url.com', $appEntity->getBaseAppUrl());

        $this->assertDefaultActionButtons();
        $this->assertDefaultModules($appEntity);
        $this->assertDefaultPrivileges($appEntity->getAclRoleId());
        $this->assertDefaultCustomFields($appEntity->getId());
        $this->assertDefaultWebhooks($appEntity->getId());
        $this->assertDefaultTemplate($appEntity->getId());
        $this->assertDefaultScript($appEntity->getId());
        $this->assertDefaultPaymentMethods($appEntity->getId());
        $this->assertDefaultCmsBlocks($appEntity->getId());
        $this->assertAssetExists($appEntity->getName());
        $this->assertFlowActionExists($appEntity->getId());
        $this->assertDefaultHosts($appEntity);
    }

    public function testInstallRollbacksRegistrationFailure(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $this->appendNewResponse(new Response(500));

        $wasThrown = false;

        try {
            $this->appLifecycle->install($manifest, true, $this->context);
        } catch (AppRegistrationException $e) {
            $wasThrown = true;
        }

        static::assertTrue($wasThrown);
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getTotal();

        static::assertEquals(0, $apps);
    }

    public function testInstallMinimalManifest(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/minimal/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        $appEntity = $apps->first();
        static::assertNotNull($appEntity);
        static::assertEquals('minimal', $appEntity->getName());
    }

    public function testInstallOnlyCallsAppLifecycleScriptsForAffectedApps(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(AppInstalledHook::HOOK_NAME, $traces);
        static::assertCount(1, $traces[AppInstalledHook::HOOK_NAME]);
        static::assertEquals('installed', $traces[AppInstalledHook::HOOK_NAME][0]['output'][0]);

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/minimal/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(AppInstalledHook::HOOK_NAME, $traces);
        static::assertCount(1, $traces[AppInstalledHook::HOOK_NAME]);
    }

    public function testInstallWithoutDescription(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/withoutDescription/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        $appEntity = $apps->first();
        static::assertNotNull($appEntity);
        static::assertEquals('withoutDescription', $appEntity->getName());
        static::assertNull($appEntity->getDescription());
    }

    public function testInstallDoesNotInstallElementsThatNeedSecretIfNoSetupIsProvided(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/Registration/_fixtures/no-setup/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        $criteria = new Criteria();
        $criteria->addAssociation('webhooks');
        /** @var AppCollection $apps */
        $apps = $this->appRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(1, $apps);

        $appEntity = $apps->first();
        static::assertNotNull($appEntity);
        static::assertCount(0, $appEntity->getModules());

        $webhookCollection = $appEntity->getWebhooks();
        static::assertInstanceOf(WebhookCollection::class, $webhookCollection);
        static::assertCount(0, $webhookCollection);
    }

    public function testInstallWithSystemDefaultLanguageNotProvidedByApp(): void
    {
        $this->setNewSystemLanguage('nl-NL');
        $this->setNewSystemLanguage('en-GB');
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $this->appLifecycle->install($manifest, true, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        $appEntity = $apps->first();
        static::assertNotNull($appEntity);
        static::assertEquals('test', $appEntity->getName());
        static::assertEquals('Test for App System', $appEntity->getDescription());
    }

    public function testInstallSavesConfig(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/withConfig/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        $appEntity = $apps->first();
        static::assertNotNull($appEntity);
        static::assertEquals('withConfig', $appEntity->getName());
        static::assertTrue($appEntity->isConfigurable());

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        static::assertEquals([
            'withConfig.config.email' => 'no-reply@shopware.de',
        ], $systemConfigService->getDomain('withConfig.config'));
    }

    public function testInstallThrowsIfConfigContainsComponentElement(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/withInvalidConfig/manifest.xml');

        static::expectException(InvalidAppConfigurationException::class);
        $this->appLifecycle->install($manifest, true, $this->context);
    }

    public function testInstallAndUpdateSavesRuleConditions(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/withRuleConditions/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        $criteria = new Criteria();
        $criteria->addAssociation('scriptConditions');
        /** @var AppCollection $apps */
        $apps = $this->appRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(1, $apps);
        $appEntity = $apps->first();
        static::assertNotNull($appEntity);
        static::assertEquals('withRuleConditions', $appEntity->getName());
        /** @var AppScriptConditionCollection $scriptCollection */
        $scriptCollection = $appEntity->getScriptConditions();
        static::assertCount(14, $scriptCollection);

        foreach ($scriptCollection as $scriptCondition) {
            static::assertStringContainsString('app\withRuleConditions_', $scriptCondition->getIdentifier());
            static::assertStringContainsString('{% return true %}', (string) $scriptCondition->getScript());
            static::assertIsArray($scriptCondition->getConfig());

            $this->assertScriptConditionFieldConfig($scriptCondition);
        }

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/withRuleConditionsUpdated/manifest.xml');
        $this->appLifecycle->update($manifest, ['id' => $appEntity->getId(), 'roleId' => Uuid::randomHex()], $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search($criteria, $this->context)->getEntities();
        $appEntity = $apps->first();
        static::assertNotNull($appEntity);

        /** @var AppScriptConditionCollection $scriptCollection */
        $scriptCollection = $appEntity->getScriptConditions();
        static::assertCount(1, $scriptCollection);
        $appScriptConditionEntity = $scriptCollection->first();
        static::assertNotNull($appScriptConditionEntity);
        $identifier = $appScriptConditionEntity->getIdentifier();
        static::assertIsString($identifier);
        static::assertEquals('app\withRuleConditions_testcondition0', $identifier);
        $constraints = $appScriptConditionEntity->getConstraints();
        static::assertIsArray($constraints);
        static::assertArrayHasKey('number', $constraints);

        $config = $appScriptConditionEntity->getConfig();
        static::assertIsArray($config);
        static::assertCount(1, $config);
        static::assertArrayHasKey(0, $config);
        static::assertIsArray($config[0]);
        static::assertArrayHasKey('type', $config[0]);
        static::assertEquals('int', $config[0]['type']);
    }

    public function testInstallThrowsIfAppIsAlreadyInstalled(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/withoutDescription/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        static::expectException(AppAlreadyInstalledException::class);
        $this->appLifecycle->install($manifest, true, $this->context);
    }

    public function testUpdateInactiveApp(): void
    {
        $id = Uuid::randomHex();
        $roleId = Uuid::randomHex();

        $this->appRepository->create([[
            'id' => $id,
            'name' => 'test',
            'path' => __DIR__ . '/../Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'Swag App',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'modules' => [
                [
                    'label' => [
                        'en-GB' => 'will be overwritten',
                    ],
                    'source' => 'https://example.com',
                ],
            ],
            'actionButtons' => [
                [
                    'action' => 'test',
                    'entity' => 'order',
                    'view' => 'detail',
                    'label' => 'test',
                    'url' => 'test.com',
                ],
                [
                    'action' => 'viewOrder',
                    'entity' => 'should',
                    'view' => 'get',
                    'label' => 'updated',
                    'url' => 'test.com',
                ],
            ],
            'integration' => [
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'customFieldSets' => [
                [
                    'name' => 'test',
                ],
            ],
            'aclRole' => [
                'id' => $roleId,
                'name' => 'test',
            ],
            'webhooks' => [
                [
                    'name' => 'hook1',
                    'url' => 'oldUrl.com',
                    'eventName' => 'testEvent',
                ],
                [
                    'name' => 'shouldGetDeleted',
                    'url' => 'test.com',
                    'eventName' => 'anotherTest',
                ],
            ],
            'templates' => [
                [
                    'path' => 'storefront/layout/header/logo.html.twig',
                    'template' => 'will be overwritten',
                    'active' => false,
                ],
                [
                    'path' => 'storefront/got/removed',
                    'template' => 'will be removed',
                    'active' => false,
                ],
            ],
            'paymentMethods' => [
                [
                    'paymentMethod' => [
                        'handlerIdentifier' => 'app\\test\\myMethod',
                        'name' => 'My method',
                        'active' => false,
                        'media' => [
                            'private' => true,
                        ],
                    ],
                    'appName' => 'test',
                    'identifier' => 'myMethod',
                ],
                [
                    'paymentMethod' => [
                        'handlerIdentifier' => 'app\\test\\toBeRemoved',
                        'name' => 'This method shall be removed',
                        'active' => false,
                    ],
                    'appName' => 'test',
                    'identifier' => 'toBeRemoved',
                ],
            ],
        ]], Context::createDefaultContext());

        $permissionPersister = $this->getContainer()->get(PermissionPersister::class);
        $permissions = Permissions::fromArray([
            'product' => ['update'],
        ]);

        $permissionPersister->updatePrivileges($permissions, $roleId);

        $app = [
            'id' => $id,
            'roleId' => $roleId,
        ];

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $eventWasReceived = false;
        $onAppUpdated = function (AppUpdatedEvent $event) use (&$eventWasReceived, $id, $manifest): void {
            $eventWasReceived = true;
            static::assertEquals($id, $event->getApp()->getId());
            static::assertEquals($manifest, $event->getManifest());
        };
        $this->eventDispatcher->addListener(AppUpdatedEvent::class, $onAppUpdated);

        $this->appLifecycle->update($manifest, $app, $this->context);

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(AppUpdatedHook::HOOK_NAME, $traces);
        static::assertEquals('updated', $traces[AppUpdatedHook::HOOK_NAME][0]['output'][0]);

        static::assertTrue($eventWasReceived);
        $this->eventDispatcher->removeListener(AppUpdatedEvent::class, $onAppUpdated);
        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        $appEntity = $apps->first();
        static::assertNotNull($appEntity);
        static::assertEquals('test', $appEntity->getName());
        static::assertEquals(
            base64_encode((string) file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/icon.png')),
            $appEntity->getIcon()
        );
        static::assertEquals('1.0.0', $appEntity->getVersion());
        static::assertNotEquals('test', $appEntity->getTranslation('label'));
        static::assertTrue($appEntity->getAllowDisable());

        $this->assertDefaultActionButtons();
        $this->assertDefaultModules($appEntity);
        $this->assertDefaultPrivileges($appEntity->getAclRoleId());
        $this->assertDefaultCustomFields($id);
        $this->assertDefaultWebhooks($appEntity->getId());
        $this->assertDefaultTemplate($appEntity->getId(), false);
        $this->assertDefaultScript($appEntity->getId(), false);
        $this->assertDefaultPaymentMethods($appEntity->getId());
        $this->assertAssetExists($appEntity->getName());
        $this->assertFlowActionExists($appEntity->getId());
        $this->assertDefaultHosts($appEntity);
    }

    public function testUpdateActiveApp(): void
    {
        $id = Uuid::randomHex();
        $roleId = Uuid::randomHex();

        $this->appRepository->create([[
            'id' => $id,
            'name' => 'test',
            'path' => __DIR__ . '/../Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'Swag App',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'baseAppUrl' => 'toBeUpdated',
            'active' => true,
            'modules' => [
                [
                    'label' => [
                        'en-GB' => 'will be overwritten',
                    ],
                    'source' => 'https://example.com',
                ],
            ],
            'actionButtons' => [
                [
                    'action' => 'test',
                    'entity' => 'order',
                    'view' => 'detail',
                    'label' => 'test',
                    'url' => 'test.com',
                ],
                [
                    'action' => 'viewOrder',
                    'entity' => 'should',
                    'view' => 'get',
                    'label' => 'updated',
                    'url' => 'test.com',
                ],
            ],
            'integration' => [
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'customFieldSets' => [
                [
                    'name' => 'test',
                ],
            ],
            'aclRole' => [
                'id' => $roleId,
                'name' => 'test',
            ],
            'webhooks' => [
                [
                    'name' => 'hook1',
                    'url' => 'oldUrl.com',
                    'eventName' => 'testEvent',
                ],
                [
                    'name' => 'shouldGetDeleted',
                    'url' => 'test.com',
                    'eventName' => 'anotherTest',
                ],
            ],
            'templates' => [
                [
                    'path' => 'storefront/layout/header/logo.html.twig',
                    'template' => 'will be overwritten',
                    'active' => true,
                ],
                [
                    'path' => 'storefront/got/removed',
                    'template' => 'will be removed',
                    'active' => true,
                ],
            ],
            'paymentMethods' => [
                [
                    'paymentMethod' => [
                        'handlerIdentifier' => 'app\\test\\myMethod',
                        'name' => 'My method',
                        'active' => true,
                        'media' => [
                            'private' => false,
                        ],
                    ],
                    'appName' => 'test',
                    'identifier' => 'myMethod',
                ],
                [
                    'paymentMethod' => [
                        'handlerIdentifier' => 'app\\test\\toBeRemoved',
                        'name' => 'This method shall be removed',
                        'active' => true,
                    ],
                    'appName' => 'test',
                    'identifier' => 'toBeRemoved',
                ],
            ],
        ]], Context::createDefaultContext());

        $permissionPersister = $this->getContainer()->get(PermissionPersister::class);
        $permissions = Permissions::fromArray([
            'product' => ['update'],
        ]);

        $permissionPersister->updatePrivileges($permissions, $roleId);

        $app = [
            'id' => $id,
            'roleId' => $roleId,
        ];

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $eventWasReceived = false;
        $onAppUpdated = function (AppUpdatedEvent $event) use (&$eventWasReceived, $id, $manifest): void {
            $eventWasReceived = true;
            static::assertEquals($id, $event->getApp()->getId());
            static::assertEquals($manifest, $event->getManifest());
        };
        $this->eventDispatcher->addListener(AppUpdatedEvent::class, $onAppUpdated);

        $this->appLifecycle->update($manifest, $app, $this->context);

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(AppUpdatedHook::HOOK_NAME, $traces);
        static::assertEquals('updated', $traces[AppUpdatedHook::HOOK_NAME][0]['output'][0]);

        static::assertTrue($eventWasReceived);
        $this->eventDispatcher->removeListener(AppUpdatedEvent::class, $onAppUpdated);
        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        $appEntity = $apps->first();
        static::assertNotNull($appEntity);
        static::assertEquals('test', $appEntity->getName());
        static::assertEquals(
            base64_encode((string) file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/icon.png')),
            $appEntity->getIcon()
        );
        static::assertEquals('1.0.0', $appEntity->getVersion());
        static::assertEquals('https://base-url.com', $appEntity->getBaseAppUrl());
        static::assertNotEquals('test', $appEntity->getTranslation('label'));
        static::assertTrue($appEntity->getAllowDisable());

        $this->assertDefaultActionButtons();
        $this->assertDefaultModules($appEntity);
        $this->assertDefaultPrivileges($appEntity->getAclRoleId());
        $this->assertDefaultCustomFields($id);
        $this->assertDefaultWebhooks($appEntity->getId());
        $this->assertDefaultTemplate($appEntity->getId());
        $this->assertDefaultScript($appEntity->getId());
        $this->assertDefaultPaymentMethods($appEntity->getId());
        $this->assertAssetExists($appEntity->getName());
        $this->assertFlowActionExists($appEntity->getId());
        $this->assertDefaultHosts($appEntity);
    }

    public function testUpdateDoesRunRegistrationIfNecessary(): void
    {
        $id = Uuid::randomHex();
        $roleId = Uuid::randomHex();

        $this->appRepository->create([[
            'id' => $id,
            'active' => true,
            'name' => 'test',
            'path' => __DIR__ . '/../Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'Swag App',
            'accessToken' => 'test',
            'integration' => [
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'customFieldSets' => [
                [
                    'name' => 'test',
                ],
            ],
            'aclRole' => [
                'id' => $roleId,
                'name' => 'test',
            ],
            'templates' => [
                [
                    'path' => 'storefront/layout/header/logo.html.twig',
                    'template' => 'will be overwritten',
                    'active' => true,
                ],
                [
                    'path' => 'storefront/got/removed',
                    'template' => 'will be removed',
                    'active' => true,
                ],
            ],
        ]], Context::createDefaultContext());

        $permissionPersister = $this->getContainer()->get(PermissionPersister::class);
        $permissions = Permissions::fromArray([
            'product' => ['update'],
        ]);

        $permissionPersister->updatePrivileges($permissions, $roleId);

        $app = [
            'id' => $id,
            'roleId' => $roleId,
        ];

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $this->appLifecycle->update($manifest, $app, $this->context);

        static::assertTrue($this->didRegisterApp());

        $criteria = new Criteria();
        $criteria->addAssociation('actionButtons');
        $criteria->addAssociation('webhooks');
        $criteria->addAssociation('paymentMethods');
        /** @var AppCollection $apps */
        $apps = $this->appRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(1, $apps);

        $this->assertDefaultActionButtons();
        $app1 = $apps->first();
        static::assertNotNull($app1);
        $this->assertDefaultModules($app1);
        $this->assertDefaultPrivileges($app1->getAclRoleId());
        $this->assertDefaultCustomFields($id);
        $this->assertDefaultWebhooks($app1->getId());
        $this->assertDefaultTemplate($app1->getId());
        $this->assertDefaultScript($app1->getId());
        $this->assertDefaultPaymentMethods($app1->getId());
        $this->assertAssetExists($app1->getName());
        $this->assertFlowActionExists($app1->getId());
        $this->assertDefaultHosts($app1);
    }

    public function testUpdateSetsConfiguration(): void
    {
        $id = Uuid::randomHex();
        $roleId = Uuid::randomHex();
        $path = str_replace($this->getContainer()->getParameter('kernel.project_dir') . '/', '', __DIR__ . '/../Manifest/_fixtures/withConfig');

        $this->appRepository->create([[
            'id' => $id,
            'name' => 'withConfig',
            'path' => $path,
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'integration' => [
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $roleId,
                'name' => 'withConfig',
            ],
        ]], Context::createDefaultContext());

        $app = [
            'id' => $id,
            'roleId' => $roleId,
        ];

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/withConfig/manifest.xml');

        $this->appLifecycle->update($manifest, $app, $this->context);

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        static::assertEquals([
            'withConfig.config.email' => 'no-reply@shopware.de',
        ], $systemConfigService->getDomain('withConfig.config'));

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        $appEntity = $apps->first();
        static::assertNotNull($appEntity);
        static::assertTrue($appEntity->isConfigurable());
    }

    public function testUpdateDoesNotOverrideConfiguration(): void
    {
        $id = Uuid::randomHex();
        $roleId = Uuid::randomHex();
        $path = str_replace($this->getContainer()->getParameter('kernel.project_dir') . '/', '', __DIR__ . '/../Manifest/_fixtures/withConfig');

        $this->appRepository->create([[
            'id' => $id,
            'name' => 'withConfig',
            'path' => $path,
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'integration' => [
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $roleId,
                'name' => 'withConfig',
            ],
        ]], Context::createDefaultContext());

        $app = [
            'id' => $id,
            'roleId' => $roleId,
        ];

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/withConfig/manifest.xml');

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('withConfig.config.email', 'my-shop@test.com');

        $this->appLifecycle->update($manifest, $app, $this->context);

        static::assertEquals([
            'withConfig.config.email' => 'my-shop@test.com',
        ], $systemConfigService->getDomain('withConfig.config'));
    }

    public function testUpdateDoesClearJsonFieldsIfTheyAreNotPresentInManifest(): void
    {
        $id = Uuid::randomHex();
        $roleId = Uuid::randomHex();
        $path = str_replace($this->getContainer()->getParameter('kernel.project_dir') . '/', '', __DIR__ . '/../Manifest/_fixtures/withConfig');

        $this->appRepository->create([[
            'id' => $id,
            'name' => 'withConfig',
            'path' => $path,
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'modules' => [['test']],
            'cookies' => [['test']],
            'mainModule' => ['test'],
            'appSecret' => 'iamsecret',
            'integration' => [
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $roleId,
                'name' => 'SwagApp',
            ],
        ]], Context::createDefaultContext());

        $app = [
            'id' => $id,
            'roleId' => $roleId,
        ];

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/minimal/manifest.xml');

        $this->appLifecycle->update($manifest, $app, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        $appEntity = $apps->first();
        static::assertNotNull($appEntity);
        static::assertEmpty($appEntity->getModules());
        static::assertEmpty($appEntity->getCookies());
        static::assertNull($appEntity->getMainModule());
    }

    public function testDelete(): void
    {
        $appId = Uuid::randomHex();
        $roleId = Uuid::randomHex();
        $integrationId = Uuid::randomHex();

        $this->appRepository->create([[
            'id' => $appId,
            'name' => 'Test',
            'path' => __DIR__ . '/../Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'actionButtons' => [
                [
                    'entity' => 'order',
                    'view' => 'detail',
                    'action' => 'test',
                    'label' => 'test',
                    'url' => 'test.com',
                ],
            ],
            'integration' => [
                'id' => $integrationId,
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $roleId,
                'name' => 'Test',
            ],
            'scripts' => [
                [
                    'name' => 'app-deleted/delete.script.twig',
                    'hook' => 'app-deleted',
                    'script' => '{% do debug.dump(\'deleted\') %}',
                    'active' => true,
                ],
            ],
        ]], Context::createDefaultContext());

        $app = [
            'id' => $appId,
            'roleId' => $roleId,
        ];

        $eventWasReceived = false;
        $onAppDeleted = function (AppDeletedEvent $event) use (&$eventWasReceived, $appId): void {
            $eventWasReceived = true;
            static::assertEquals($appId, $event->getAppId());
        };
        $this->eventDispatcher->addListener(AppDeletedEvent::class, $onAppDeleted);

        $this->appLifecycle->delete('Test', $app, $this->context);

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(AppDeletedHook::HOOK_NAME, $traces);
        static::assertEquals('deleted', $traces[AppDeletedHook::HOOK_NAME][0]['output'][0]);

        static::assertTrue($eventWasReceived);
        $this->eventDispatcher->removeListener(AppDeletedEvent::class, $onAppDeleted);
        $apps = $this->appRepository->searchIds(new Criteria([$appId]), $this->context)->getIds();
        static::assertCount(0, $apps);

        /** @var EntityRepository $aclRoleRepository */
        $aclRoleRepository = $this->getContainer()->get('acl_role.repository');
        $roles = $aclRoleRepository->searchIds(new Criteria([$roleId]), $this->context)->getIds();
        static::assertCount(1, $roles);

        /** @var EntityRepository $integrationRepository */
        $integrationRepository = $this->getContainer()->get('integration.repository');
        $integrations = $integrationRepository->searchIds(new Criteria([$integrationId]), $this->context)->getIds();
        static::assertCount(1, $integrations);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $apps = $this->actionButtonRepository->searchIds($criteria, $this->context)->getIds();
        static::assertCount(0, $apps);
    }

    public function testDeleteAppDispatchedOnce(): void
    {
        $appId = Uuid::randomHex();
        $roleId = Uuid::randomHex();

        $this->appRepository->create([[
            'id' => $appId,
            'name' => 'Test',
            'path' => __DIR__ . '/../Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'actionButtons' => [
                [
                    'entity' => 'order',
                    'view' => 'detail',
                    'action' => 'test',
                    'label' => 'test',
                    'url' => 'test.com',
                ],
            ],
            'integration' => [
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $roleId,
                'name' => 'Test',
            ],
        ]], Context::createDefaultContext());

        $app = [
            'id' => $appId,
            'roleId' => $roleId,
        ];

        $countEventDispatched = 0;
        $onAppDeleted = function (AppDeletedEvent $event) use (&$countEventDispatched, $appId): void {
            ++$countEventDispatched;
            static::assertEquals($appId, $event->getAppId());
        };
        $this->eventDispatcher->addListener(AppDeletedEvent::class, $onAppDeleted);

        $this->appLifecycle->delete('Test', $app, $this->context);

        $this->eventDispatcher->removeListener(AppDeletedEvent::class, $onAppDeleted);

        static::assertSame(1, $countEventDispatched);
    }

    public function testDeleteWithCustomFields(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(1, $apps);

        $filesystem = $this->getContainer()->get('shopware.filesystem.asset');
        static::assertTrue($filesystem->has('bundles/test/asset.txt'));

        $app = [
            'id' => $apps->first()->getId(),
            'roleId' => $apps->first()->getAclRoleId(),
        ];

        $this->appLifecycle->delete('test', $app, $this->context);

        $apps = $this->appRepository->searchIds(new Criteria(), $this->context)->getIds();
        static::assertCount(0, $apps);

        static::assertFalse($filesystem->has('bundles/test/asset.txt'));
    }

    public function testDeleteAppDeletesConfigWhenUserDataShouldNotBeKept(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/withConfig/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        $appEntity = $apps->first();
        static::assertNotNull($appEntity);
        static::assertEquals('withConfig', $appEntity->getName());
        /** @var AppEntity $app */
        $app = $appEntity;

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        static::assertEquals([
            'withConfig.config.email' => 'no-reply@shopware.de',
        ], $systemConfigService->getDomain('withConfig.config'));

        $this->appLifecycle->delete('withConfig', ['id' => $app->getId()], $this->context);

        static::assertEquals([], $systemConfigService->getDomain('withConfig.config'));
    }

    public function testDeleteAppDoesNotDeleteConfigWhenUserDataShouldBeKept(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/withConfig/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        $appEntity = $apps->first();
        static::assertNotNull($appEntity);
        static::assertEquals('withConfig', $appEntity->getName());
        /** @var AppEntity $app */
        $app = $appEntity;

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        static::assertEquals([
            'withConfig.config.email' => 'no-reply@shopware.de',
        ], $systemConfigService->getDomain('withConfig.config'));

        $this->appLifecycle->delete('withConfig', ['id' => $app->getId()], $this->context, true);

        static::assertEquals([
            'withConfig.config.email' => 'no-reply@shopware.de',
        ], $systemConfigService->getDomain('withConfig.config'));
    }

    public function testInstallWithUpdateAclRole(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $userId = Uuid::randomHex();
        $this->createUser($userId);

        $aclRoleId = Uuid::randomHex();
        $this->createAclRole($aclRoleId, ['app.all']);

        $this->createAclUserRole($userId, $aclRoleId);

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $this->appLifecycle->install($manifest, true, $this->context);

        $criteria = new Criteria();
        $criteria->addAssociation('integration');
        /** @var AppCollection $apps */
        $apps = $this->appRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(1, $apps);
        $appEntity = $apps->first();
        static::assertNotNull($appEntity);
        static::assertEquals('test', $appEntity->getName());

        $privileges = $connection->fetchOne('
            SELECT `privileges`
            FROM `acl_role`
            WHERE `id` = :aclRoleId
        ', ['aclRoleId' => Uuid::fromHexToBytes($aclRoleId)]);

        static::assertIsString($privileges);
        $privileges = json_decode($privileges, true);

        static::assertContains('app.' . $appEntity->getName(), $privileges);
    }

    public function testDeleteWithDeleteAclRole(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(1, $apps);

        $aclRoleId = Uuid::randomHex();
        $appPrivilege = 'app.' . $apps->first()->getName();
        $this->createAclRole($aclRoleId, [$appPrivilege]);

        $app = [
            'id' => $apps->first()->getId(),
            'roleId' => $apps->first()->getAclRoleId(),
        ];

        $this->appLifecycle->delete('test', $app, $this->context);

        $apps = $this->appRepository->searchIds(new Criteria(), $this->context)->getIds();
        static::assertCount(0, $apps);

        /** @var EntityRepository $aclRoleRepository */
        $aclRoleRepository = $this->getContainer()->get('acl_role.repository');
        $aclRole = $aclRoleRepository->search(new Criteria([$aclRoleId]), $this->context)->first();

        static::assertNotContains($appPrivilege, $aclRole->getPrivileges());
    }

    public function testInstallWithAllowedHosts(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/withAllowedHosts/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        $app = $apps->first();
        static::assertNotNull($app);
        static::assertEquals('withAllowedHosts', $app->getName());

        $allowedHosts = $app->getAllowedHosts();
        static::assertIsArray($allowedHosts);
        static::assertCount(2, $allowedHosts);
        static::assertTrue(\in_array('shopware.com', $allowedHosts, true));
        static::assertTrue(\in_array('example.com', $allowedHosts, true));
    }

    public function testUpdateFlowActionApp(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);
        $app = $this->appRepository->search(new Criteria(), $this->context)->first();

        $appFlowActions = $this->getAppFlowActions($app->getId());
        static::assertIsArray($appFlowActions);
        static::assertArrayHasKey(0, $appFlowActions);

        $newManifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest1_1_0.xml');
        $this->appLifecycle->update(
            $newManifest,
            [
                'id' => $app->getId(),
                'roleId' => $app->getAclRoleId(),
            ],
            $this->context
        );

        $newVersion = $this->appRepository->search(new Criteria(), $this->context)->first();
        static::assertEquals('1.1.0', $newVersion->getVersion());

        $newAppFlowActions = $this->getAppFlowActions($app->getId());
        static::assertIsArray($newAppFlowActions);
        static::assertArrayHasKey(0, $newAppFlowActions);

        static::assertEquals($appFlowActions[0], $newAppFlowActions[0]);
    }

    public function testRefreshFlowActions(): void
    {
        $context = Context::createDefaultContext();
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        $appId = $this->getAppId();
        static::assertIsString($appId);

        $flowActions = $this->getAppFlowActions($appId);
        static::assertIsArray($flowActions);

        $flowAction = FlowAction::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/Resources/flow-action-v2.xml');
        $flowActionPersister = $this->getContainer()->get(FlowActionPersister::class);
        $flowActionPersister->updateActions($flowAction, $appId, $context, 'en-GB');

        $newFlowActions = $this->getAppFlowActions($appId);
        static::assertIsArray($newFlowActions);
        static::assertCount(2, $newFlowActions);
        foreach ($flowActions as $action) {
            static::assertTrue(\in_array($action['id'], array_column($newFlowActions, 'id'), true));
        }
    }

    public function testRefreshFlowActionsWithAnotherAction(): void
    {
        $context = Context::createDefaultContext();
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        $appId = $this->getAppId();
        static::assertIsString($appId);

        $flowActions = $this->getAppFlowActions($appId);
        static::assertIsArray($flowActions);

        $flowAction = FlowAction::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/Resources/flow-action-v3.xml');
        $flowActionPersister = $this->getContainer()->get(FlowActionPersister::class);
        $flowActionPersister->updateActions($flowAction, $appId, $context, 'en-GB');

        $newFlowActions = $this->getAppFlowActions($appId);
        static::assertIsArray($newFlowActions);
        static::assertCount(1, $newFlowActions);
        foreach ($flowActions as $action) {
            static::assertFalse(\in_array($action['id'], array_column($newFlowActions, 'id'), true));
        }
    }

    public function testRefreshFlowActionUsedInFlowBuilder(): void
    {
        $context = Context::createDefaultContext();
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        $appId = $this->getAppId();
        static::assertIsString($appId);

        $flowActions = $this->getAppFlowActions($appId);
        static::assertIsArray($flowActions);
        static::assertArrayHasKey(0, $flowActions);
        static::assertIsArray($flowActions[0]);
        static::assertArrayHasKey('id', $flowActions[0]);

        $flowId = Uuid::randomHex();
        $this->createFlow($flowId);

        $sequenceId = Uuid::randomHex();
        $this->createSequence($sequenceId, $flowId, $flowActions[0]['id']);

        $flowAction = FlowAction::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/Resources/flow-action-v2.xml');
        $flowActionPersister = $this->getContainer()->get(FlowActionPersister::class);
        $flowActionPersister->updateActions($flowAction, $appId, $context, 'en-GB');

        $appFlowActionId = $this->getAppFlowActionIdFromSequence($sequenceId);
        static::assertEquals($appFlowActionId, $flowActions[0]['id']);
    }

    private function getAppFlowActionIdFromSequence(string $sequenceId): ?string
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('lower(hex(app_flow_action_id))');
        $query->from('flow_sequence');
        $query->where('id = :id');
        $query->setParameter('id', Uuid::fromHexToBytes($sequenceId));

        return $query->executeQuery()->fetchOne() ?: null;
    }

    private function createFlow(string $flowId): void
    {
        $this->connection->insert('flow', [
            'id' => Uuid::fromHexToBytes($flowId),
            'name' => 'Test Flow',
            'event_name' => 'checkout.order.placed',
            'priority' => 1,
            'active' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createSequence(string $sequenceId, string $flowId, string $appFlowActionId): void
    {
        $this->connection->insert('flow_sequence', [
            'id' => Uuid::fromHexToBytes($sequenceId),
            'flow_id' => Uuid::fromHexToBytes($flowId),
            'app_flow_action_id' => Uuid::fromHexToBytes($appFlowActionId),
            'action_name' => 'app.telegram.send.message',
            'position' => 1,
            'display_group' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getAppFlowActions(string $appId): ?array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('lower(hex(id)) AS id');
        $query->from('app_flow_action');
        $query->where('app_id = :appId');
        $query->setParameter('appId', Uuid::fromHexToBytes($appId));

        return $query->executeQuery()->fetchAllAssociative() ?: null;
    }

    private function getAppId(): ?string
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('lower(hex(id))');
        $query->from('app');

        return $query->executeQuery()->fetchOne() ?: null;
    }

    private function assertDefaultActionButtons(): void
    {
        $actionButtons = $this->actionButtonRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(2, $actionButtons);
        $actionNames = array_map(function (ActionButtonEntity $actionButton) {
            return $actionButton->getAction();
        }, $actionButtons->getElements());

        static::assertContains('viewOrder', $actionNames);
        static::assertContains('doStuffWithProducts', $actionNames);
    }

    private function assertDefaultModules(AppEntity $app): void
    {
        static::assertCount(2, $app->getModules());

        static::assertEquals([
            [
                'label' => [
                    'en-GB' => 'My first own module',
                    'de-DE' => 'Mein erstes eigenes Modul',
                ],
                'source' => 'https://test.com',
                'name' => 'first-module',
                'parent' => 'sw-test-structure-module',
                'position' => 10,
            ], [
                'label' => [
                    'en-GB' => 'My menu entry for modules',
                    'de-DE' => 'Mein Menüeintrag für Module',
                ],
                'source' => null,
                'name' => 'structure-module',
                'parent' => 'sw-catalogue',
                'position' => 50,
            ],
        ], $app->getModules());
    }

    private function assertDefaultPrivileges(string $roleId): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $privileges = $connection->fetchOne('
            SELECT `privileges`
            FROM `acl_role`
            WHERE `id` = :aclRoleId
        ', ['aclRoleId' => Uuid::fromHexToBytes($roleId)]);

        $privileges = json_decode($privileges, true);

        static::assertCount(16, $privileges);

        static::assertContains('product:read', $privileges);
        static::assertContains('product:create', $privileges);
        static::assertContains('product:update', $privileges);
        static::assertContains('product:delete', $privileges);
        static::assertContains('category:read', $privileges);
        static::assertContains('category:delete', $privileges);
        static::assertContains('product_manufacturer:read', $privileges);
        static::assertContains('product_manufacturer:create', $privileges);
        static::assertContains('product_manufacturer:delete', $privileges);
        static::assertContains('tax:read', $privileges);
        static::assertContains('tax:create', $privileges);
        static::assertContains('language:read', $privileges);
        static::assertContains('custom_field_set:read', $privileges);
        static::assertContains('custom_field_set:update', $privileges);
        static::assertContains('order:read', $privileges);
        static::assertContains('user_change_me', $privileges);
    }

    private function assertDefaultCustomFields(string $appId): void
    {
        /** @var EntityRepository $customFieldSetRepository */
        $customFieldSetRepository = $this->getContainer()->get('custom_field_set.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addAssociation('relations');
        $criteria->addAssociation('customFields');

        /** @var CustomFieldSetCollection $customFieldSets */
        $customFieldSets = $customFieldSetRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(1, $customFieldSets);

        $customFieldSet = $customFieldSets->first();
        static::assertNotNull($customFieldSet);
        static::assertEquals('custom_field_test', $customFieldSet->getName());
        static::assertCount(2, $customFieldSet->getRelations() ?? []);

        $relations = $customFieldSet->getRelations();
        static::assertNotNull($relations);

        $relatedEntities = array_map(function (CustomFieldSetRelationEntity $relation) {
            return $relation->getEntityName();
        }, $relations->getElements());
        static::assertContains('product', $relatedEntities);
        static::assertContains('customer', $relatedEntities);

        static::assertEquals([
            'label' => [
                'en-GB' => 'Custom field test',
                'de-DE' => 'Zusatzfeld Test',
            ],
            'translated' => true,
        ], $customFieldSet->getConfig());
        static::assertTrue($customFieldSet->isGlobal());

        $customFieldCollection = $customFieldSet->getCustomFields();
        static::assertInstanceOf(CustomFieldCollection::class, $customFieldCollection);

        static::assertCount(2, $customFieldCollection);

        $fieldWithoutAllowWrite = $customFieldCollection->filterByProperty('name', 'bla_test')->first();
        static::assertInstanceOf(CustomFieldEntity::class, $fieldWithoutAllowWrite);

        static::assertFalse($fieldWithoutAllowWrite->isAllowCustomerWrite());

        $fieldWithAllowWrite = $customFieldCollection->filterByProperty('name', 'bla_test2')->first();
        static::assertInstanceOf(CustomFieldEntity::class, $fieldWithAllowWrite);

        static::assertTrue($fieldWithAllowWrite->isAllowCustomerWrite());
    }

    private function assertDefaultWebhooks(string $appId): void
    {
        /** @var EntityRepository $webhookRepository */
        $webhookRepository = $this->getContainer()->get('webhook.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        $webhooks = $webhookRepository->search($criteria, $this->context)->getElements();

        static::assertCount(3, $webhooks);

        usort($webhooks, static function (WebhookEntity $a, WebhookEntity $b): int {
            return $a->getUrl() <=> $b->getUrl();
        });

        /** @var WebhookEntity $firstWebhook */
        $firstWebhook = $webhooks[0];
        static::assertEquals('https://test-flow.com', $firstWebhook->getUrl());
        static::assertEquals('telegram.send.message', $firstWebhook->getEventName());

        /** @var WebhookEntity $firstWebhook */
        $secondWebhook = $webhooks[1];
        static::assertEquals('https://test.com/hook', $secondWebhook->getUrl());
        static::assertEquals('checkout.customer.before.login', $secondWebhook->getEventName());

        /** @var WebhookEntity $thirdWebhook */
        $thirdWebhook = $webhooks[2];
        static::assertEquals('https://test.com/hook2', $thirdWebhook->getUrl());
        static::assertEquals('checkout.order.placed', $thirdWebhook->getEventName());
    }

    private function assertDefaultTemplate(string $appId, bool $active = true): void
    {
        /** @var EntityRepository $templateRepository */
        $templateRepository = $this->getContainer()->get('app_template.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addSorting(new FieldSorting('path', FieldSorting::ASCENDING));
        $templates = $templateRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(3, $templates);
        $templates = array_values($templates->getElements());

        /** @var TemplateEntity $template */
        $template = $templates[0];
        static::assertEquals('storefront/layout/header/header.html.twig', $template->getPath());
        static::assertStringEqualsFile(
            __DIR__ . '/../Manifest/_fixtures/test/Resources/views/storefront/layout/header/header.html.twig',
            $template->getTemplate()
        );
        static::assertEquals($active, $template->isActive());

        /** @var TemplateEntity $template */
        $template = $templates[1];
        static::assertEquals('storefront/layout/header/logo.html.twig', $template->getPath());
        static::assertStringEqualsFile(
            __DIR__ . '/../Manifest/_fixtures/test/Resources/views/storefront/layout/header/logo.html.twig',
            $template->getTemplate()
        );
        static::assertEquals($active, $template->isActive());
    }

    private function assertDefaultScript(string $appId, bool $active = true): void
    {
        /** @var EntityRepository $scriptRepository */
        $scriptRepository = $this->getContainer()->get('script.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::DESCENDING));
        $scripts = $scriptRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(6, $scripts);

        /** @var ScriptEntity $script */
        $script = $scripts->first();
        static::assertEquals('product-page-loaded/product-page-script.twig', $script->getName());
        static::assertEquals('product-page-loaded', $script->getHook());
        static::assertStringEqualsFile(
            __DIR__ . '/../Manifest/_fixtures/test/Resources/scripts/product-page-loaded/product-page-script.twig',
            $script->getScript()
        );
        static::assertEquals($active, $script->isActive());

        $cache = $this->getContainer()->get('cache.object');
        static::assertTrue($cache->hasItem(ScriptLoader::CACHE_KEY));

        $item = $cache->getItem(ScriptLoader::CACHE_KEY);
        $cachedScripts = CacheCompressor::uncompress($item);
        static::assertArrayHasKey('product-page-loaded', $cachedScripts);
        static::assertCount(1, $cachedScripts['product-page-loaded']);
        static::assertInstanceOf(Script::class, $cachedScripts['product-page-loaded'][0]);
        static::assertEquals($script->getName(), $cachedScripts['product-page-loaded'][0]->getName());
    }

    private function assertDefaultPaymentMethods(string $appId): void
    {
        /** @var EntityRepository $paymentMethodRepository */
        $paymentMethodRepository = $this->getContainer()->get('payment_method.repository');

        $criteria = new Criteria();
        $criteria->addAssociation('appPaymentMethod');
        $criteria->addFilter(new EqualsFilter('appPaymentMethod.appId', $appId));

        $paymentMethods = $paymentMethodRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(2, $paymentMethods);

        /** @var PaymentMethodEntity|null $paymentMethod */
        $paymentMethod = $paymentMethods->filterByProperty('name', 'The app payment method')->first();
        static::assertNotNull($paymentMethod);
        static::assertSame('The app payment method', $paymentMethod->getName());
        static::assertSame('handler_app_test_mymethod', $paymentMethod->getFormattedHandlerIdentifier());
        static::assertNotNull($paymentMethod->getMediaId());
        $fileLoader = $this->getContainer()->get(FileLoader::class);
        static::assertNotEmpty($fileLoader->loadMediaFile($paymentMethod->getMediaId(), $this->context));
        $appPaymentMethod = $paymentMethod->getAppPaymentMethod();
        static::assertNotNull($appPaymentMethod);
        static::assertSame('test', $appPaymentMethod->getAppName());
        static::assertSame('myMethod', $appPaymentMethod->getIdentifier());
        static::assertSame('https://payment.app/payment/process', $appPaymentMethod->getPayUrl());
        static::assertSame('https://payment.app/payment/finalize', $appPaymentMethod->getFinalizeUrl());
    }

    private function assertDefaultCmsBlocks(string $appId): void
    {
        /** @var EntityRepository $cmsBlockRepository */
        $cmsBlockRepository = $this->getContainer()->get('app_cms_block.repository');

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('appId', $appId)
        );

        $cmsBlocks = $cmsBlockRepository->search($criteria, $this->context)->getEntities();
        static::assertCount(2, $cmsBlocks);

        /** @var AppCmsBlockEntity $firstCmsBlock */
        $firstCmsBlock = $cmsBlocks->filterByProperty('name', 'my-first-block')->first();
        static::assertEquals('my-first-block', $firstCmsBlock->getName());
        static::assertEquals('First block from app', $firstCmsBlock->getLabel());
        $firstBlockJson = json_encode($firstCmsBlock->getBlock());
        static::assertIsString($firstBlockJson);
        static::assertJsonStringEqualsJsonFile(__DIR__ . '/_fixtures/cms/expectedFirstCmsBlock.json', $firstBlockJson);
        static::assertEquals(
            $this->stripWhitespace((string) file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/Resources/cms/blocks/my-first-block/preview.html')),
            $this->stripWhitespace($firstCmsBlock->getTemplate())
        );
        static::assertEquals(
            file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/Resources/cms/blocks/my-first-block/styles.css'),
            $firstCmsBlock->getStyles()
        );

        /** @var AppCmsBlockEntity $secondCmsBlock */
        $secondCmsBlock = $cmsBlocks->filterByProperty('name', 'my-second-block')->first();
        static::assertEquals('my-second-block', $secondCmsBlock->getName());
        static::assertEquals('Second block from app', $secondCmsBlock->getLabel());
        $cmsBlockJson = json_encode($secondCmsBlock->getBlock());
        static::assertIsString($cmsBlockJson);
        static::assertJsonStringEqualsJsonFile(__DIR__ . '/_fixtures/cms/expectedSecondCmsBlock.json', $cmsBlockJson);
        static::assertEquals(
            $this->stripWhitespace((string) file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/Resources/cms/blocks/my-second-block/previewExpected.html')),
            $this->stripWhitespace($secondCmsBlock->getTemplate())
        );
        static::assertEquals(
            $this->stripWhitespace((string) file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/Resources/cms/blocks/my-second-block/styles.css')),
            $this->stripWhitespace($secondCmsBlock->getStyles())
        );
    }

    private function stripWhitespace(string $text): string
    {
        return (string) preg_replace('/\s/m', '', $text);
    }

    private function setNewSystemLanguage(string $iso): void
    {
        $languageRepository = $this->getContainer()->get('language.repository');

        $localeId = $this->getIsoId($iso);
        $languageRepository->update(
            [
                [
                    'id' => Defaults::LANGUAGE_SYSTEM,
                    'name' => $iso,
                    'localeId' => $localeId,
                    'translationCodeId' => $localeId,
                ],
            ],
            Context::createDefaultContext()
        );
    }

    private function getIsoId(string $iso): string
    {
        /** @var EntityRepository $localeRepository */
        $localeRepository = $this->getContainer()->get('locale.repository');

        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('code', $iso));

        return $localeRepository->search($criteria, Context::createDefaultContext())->first()->getId();
    }

    private function createUser(string $userId): void
    {
        $this->getContainer()->get(Connection::class)->insert('user', [
            'id' => Uuid::fromHexToBytes($userId),
            'first_name' => 'test',
            'last_name' => '',
            'email' => 'test@example.com',
            'username' => 'userTest',
            'password' => password_hash('123456', \PASSWORD_BCRYPT),
            'locale_id' => Uuid::fromHexToBytes($this->getLocaleIdOfSystemLanguage()),
            'active' => 1,
            'admin' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createAclRole(string $aclRoleId, array $privileges): void
    {
        $this->getContainer()->get(Connection::class)->insert('acl_role', [
            'id' => Uuid::fromHexToBytes($aclRoleId),
            'name' => 'aclTest',
            'privileges' => json_encode($privileges),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createAclUserRole(string $userId, string $aclRoleId): void
    {
        $this->getContainer()->get(Connection::class)->insert('acl_user_role', [
            'user_id' => Uuid::fromHexToBytes($userId),
            'acl_role_id' => Uuid::fromHexToBytes($aclRoleId),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function assertAssetExists(string $appName): void
    {
        $filesystem = $this->getContainer()->get('shopware.filesystem.asset');

        static::assertTrue($filesystem->has('bundles/' . strtolower($appName) . '/asset.txt'));
    }

    private function assertScriptConditionFieldConfig(AppScriptConditionEntity $scriptCondition): void
    {
        /** @var array $constraints */
        $constraints = $scriptCondition->getConstraints();

        switch ($scriptCondition->getIdentifier()) {
            case 'app\withRuleConditions_testcondition0':
                static::assertArrayHasKey('operator', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertEquals('select', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition1':
                static::assertArrayHasKey('customerGroupIds', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertEquals('entity', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition2':
                static::assertArrayHasKey('firstName', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertEquals('text', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition3':
                static::assertArrayHasKey('number', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertEquals('int', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition4':
                static::assertArrayHasKey('number', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertEquals('float', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition5':
                static::assertArrayHasKey('productId', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertEquals('entity', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition6':
                static::assertArrayHasKey('expected', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertEquals('bool', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition7':
                static::assertArrayHasKey('datetime', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertEquals('datetime', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition8':
                static::assertArrayHasKey('colorcode', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertEquals('text', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition9':
                static::assertArrayHasKey('mediaId', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertEquals('text', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition10':
                static::assertArrayHasKey('price', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertEquals('price', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition11':
                static::assertArrayHasKey('firstName', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertEquals('html', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition12':
                static::assertArrayHasKey('multiselection', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertEquals('select', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition13':
                static::assertCount(0, $constraints);
                static::assertCount(0, $scriptCondition->getConfig() ?? []);

                break;
            default:
                static::fail(sprintf('Did not expect to find app script condition with identifier %s', $scriptCondition->getIdentifier()));
        }
    }

    private function assertFlowActionExists(string $appId): void
    {
        $appFlowAction = $this->getContainer()
            ->get(Connection::class)
            ->executeQuery('SELECT * FROM `app_flow_action` WHERE `app_id` = :id', [
                'id' => Uuid::fromHexToBytes($appId),
            ])->fetchAssociative();

        static::assertIsArray($appFlowAction);
        static::assertEquals($appFlowAction['name'], 'telegram.send.message');
        static::assertEquals($appFlowAction['url'], 'https://test-flow.com');
        static::assertEquals($appFlowAction['sw_icon'], 'default-communication-speech-bubbles');
        $parameters = json_decode($appFlowAction['parameters'], true);
        static::assertNotFalse($parameters);
        static::assertEquals(
            [
                [
                    'name' => 'message',
                    'type' => 'string',
                    'value' => 'string message',
                    'extensions' => [],
                ],
            ],
            $parameters
        );

        $config = json_decode($appFlowAction['config'], true);
        static::assertNotFalse($config);
        static::assertEquals(
            [
                [
                    'name' => 'text',
                    'type' => 'text',
                    'label' => [
                        'de-DE' => 'Text DE',
                        'en-GB' => 'Text',
                    ],
                    'options' => [],
                    'helpText' => [
                        'de-DE' => 'Help DE',
                        'en-GB' => 'Help Text',
                    ],
                    'required' => true,
                    'extensions' => [],
                    'placeHolder' => [
                        'de-DE' => 'Enter Text DE...',
                        'en-GB' => 'Enter Text...',
                    ],
                    'defaultValue' => 'Hello',
                ],
            ],
            $config
        );

        $headers = json_decode($appFlowAction['headers'], true);
        static::assertNotFalse($headers);
        static::assertEquals(
            [
                [
                    'name' => 'content-type',
                    'type' => 'string',
                    'value' => 'application/json',
                    'extensions' => [],
                ],
            ],
            $headers
        );

        $requirements = json_decode($appFlowAction['requirements'], true);
        static::assertNotFalse($requirements);
        static::assertEquals(
            [
                'orderAware',
                'customerAware',
            ],
            $requirements
        );

        $headlines = $this->getContainer()
            ->get(Connection::class)
            ->executeQuery('SELECT `headline` FROM `app_flow_action_translation` WHERE `app_flow_action_id` = :id', [
                'id' => $appFlowAction['id'],
            ])->fetchAllAssociativeIndexed();

        static::assertTrue(\in_array('The headline App Flow Action', \array_keys($headlines), true));
        static::assertTrue(\in_array('Die Überschrift App Flow Action', \array_keys($headlines), true));
    }

    private function assertDefaultHosts(AppEntity $app): void
    {
        static::assertEquals([
            'my.app.com',
            'test.com',
            'base-url.com',
            'main-module',
            'swag-test.com',
            'payment.app',
        ], $app->getAllowedHosts());
    }
}
