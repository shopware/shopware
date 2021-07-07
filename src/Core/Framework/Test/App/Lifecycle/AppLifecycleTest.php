<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Lifecycle;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\File\FileLoader;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonEntity;
use Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockEntity;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Exception\AppAlreadyInstalledException;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Exception\InvalidAppConfigurationException;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\Persister\PermissionPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Permissions;
use Shopware\Core\Framework\App\Template\TemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SystemConfigTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\WebhookEntity;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AppLifecycleTest extends TestCase
{
    use GuzzleTestClientBehaviour;
    use SystemConfigTestBehaviour;

    private AppLifecycle $appLifecycle;

    private EntityRepositoryInterface $appRepository;

    private Context $context;

    private EntityRepositoryInterface $actionButtonRepository;

    private EventDispatcherInterface $eventDispatcher;

    public function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->actionButtonRepository = $this->getContainer()->get('app_action_button.repository');

        $this->appLifecycle = $this->getContainer()->get(AppLifecycle::class);

        $this->context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');
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

        static::assertTrue($eventWasReceived);
        $this->eventDispatcher->removeListener(AppInstalledEvent::class, $onAppInstalled);
        $criteria = new Criteria();
        $criteria->addAssociation('integration');
        /** @var AppCollection $apps */
        $apps = $this->appRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('test', $apps->first()->getName());
        static::assertEquals(
            base64_encode(file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/icon.png')),
            $apps->first()->getIcon()
        );
        // assert formatting with \n and \t is preserved
        static::assertEquals(
            'Following personal information will be processed on shopware AG\'s servers:

- Name
- Billing address
- Order value',
            $apps->first()->getPrivacyPolicyExtensions()
        );

        static::assertEquals($appId, $apps->first()->getId());
        static::assertFalse($apps->first()->isConfigurable());
        static::assertFalse($apps->first()->getIntegration()->getAdmin());
        $this->assertDefaultActionButtons();
        $this->assertDefaultModules($apps->first());
        $this->assertDefaultPrivileges($apps->first()->getAclRoleId());
        $this->assertDefaultCustomFields($apps->first()->getId());
        $this->assertDefaultWebhooks($apps->first()->getId());
        $this->assertDefaultTemplate($apps->first()->getId());
        $this->assertDefaultPaymentMethods($apps->first()->getId());
        $this->assertDefaultCmsBlocks($apps->first()->getId());
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
        static::assertEquals('minimal', $apps->first()->getName());
    }

    public function testInstallWithoutDescription(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/withoutDescription/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('withoutDescription', $apps->first()->getName());
        static::assertNull($apps->first()->getDescription());
    }

    public function testInstallDoesNotInstallElementsThatNeedSecretIfNoSetupIsProvided(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/Registration/_fixtures/no-setup/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        $criteria = new Criteria();
        $criteria->addAssociation('actionButtons');
        $criteria->addAssociation('webhooks');
        /** @var AppCollection $apps */
        $apps = $this->appRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(1, $apps);

        static::assertCount(0, $apps->first()->getActionButtons());
        static::assertCount(0, $apps->first()->getModules());
        static::assertCount(0, $apps->first()->getWebhooks());
    }

    public function testInstallWithSystemDefaultLanguageNotProvidedByApp(): void
    {
        $this->setNewSystemLanguage('nl-NL');
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $this->appLifecycle->install($manifest, true, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('test', $apps->first()->getName());
        static::assertEquals('Test for App System', $apps->first()->getDescription());
    }

    public function testInstallSavesConfig(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/withConfig/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('withConfig', $apps->first()->getName());
        static::assertTrue($apps->first()->isConfigurable());

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $this->resetInternalSystemConfigCache();
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

        static::assertTrue($eventWasReceived);
        $this->eventDispatcher->removeListener(AppUpdatedEvent::class, $onAppUpdated);
        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('test', $apps->first()->getName());
        static::assertEquals(
            base64_encode(file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/icon.png')),
            $apps->first()->getIcon()
        );
        static::assertEquals('1.0.0', $apps->first()->getVersion());
        static::assertNotEquals('test', $apps->first()->getTranslation('label'));

        $this->assertDefaultActionButtons();
        $this->assertDefaultModules($apps->first());
        $this->assertDefaultPrivileges($apps->first()->getAclRoleId());
        $this->assertDefaultCustomFields($id);
        $this->assertDefaultWebhooks($apps->first()->getId());
        $this->assertDefaultTemplate($apps->first()->getId(), false);
        $this->assertDefaultPaymentMethods($apps->first()->getId());
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

        static::assertTrue($eventWasReceived);
        $this->eventDispatcher->removeListener(AppUpdatedEvent::class, $onAppUpdated);
        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('test', $apps->first()->getName());
        static::assertEquals(
            base64_encode(file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/icon.png')),
            $apps->first()->getIcon()
        );
        static::assertEquals('1.0.0', $apps->first()->getVersion());
        static::assertNotEquals('test', $apps->first()->getTranslation('label'));

        $this->assertDefaultActionButtons();
        $this->assertDefaultModules($apps->first());
        $this->assertDefaultPrivileges($apps->first()->getAclRoleId());
        $this->assertDefaultCustomFields($id);
        $this->assertDefaultWebhooks($apps->first()->getId());
        $this->assertDefaultTemplate($apps->first()->getId());
        $this->assertDefaultPaymentMethods($apps->first()->getId());
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
        $this->assertDefaultModules($apps->first());
        $this->assertDefaultPrivileges($apps->first()->getAclRoleId());
        $this->assertDefaultCustomFields($id);
        $this->assertDefaultWebhooks($apps->first()->getId());
        $this->assertDefaultTemplate($apps->first()->getId());
        $this->assertDefaultPaymentMethods($apps->first()->getId());
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
        $this->resetInternalSystemConfigCache();
        static::assertEquals([
            'withConfig.config.email' => 'no-reply@shopware.de',
        ], $systemConfigService->getDomain('withConfig.config'));

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertTrue($apps->first()->isConfigurable());
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

        $this->resetInternalSystemConfigCache();
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
        static::assertEmpty($apps->first()->getModules());
        static::assertEmpty($apps->first()->getCookies());
        static::assertNull($apps->first()->getMainModule());
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

        static::assertTrue($eventWasReceived);
        $this->eventDispatcher->removeListener(AppDeletedEvent::class, $onAppDeleted);
        $apps = $this->appRepository->searchIds(new Criteria([$appId]), $this->context)->getIds();
        static::assertCount(0, $apps);

        /** @var EntityRepositoryInterface $aclRoleRepository */
        $aclRoleRepository = $this->getContainer()->get('acl_role.repository');
        $roles = $aclRoleRepository->searchIds(new Criteria([$roleId]), $this->context)->getIds();
        static::assertCount(1, $roles);

        /** @var EntityRepositoryInterface $integrationRepository */
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

        $app = [
            'id' => $apps->first()->getId(),
            'roleId' => $apps->first()->getAclRoleId(),
        ];

        $this->appLifecycle->delete('test', $app, $this->context);

        $apps = $this->appRepository->searchIds(new Criteria(), $this->context)->getIds();
        static::assertCount(0, $apps);
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
        static::assertEquals('test', $apps->first()->getName());

        $privileges = $connection->fetchColumn('
            SELECT `privileges`
            FROM `acl_role`
            WHERE `id` = :aclRoleId
        ', ['aclRoleId' => Uuid::fromHexToBytes($aclRoleId)]);

        $privileges = json_decode($privileges, true);

        static::assertContains('app.' . $apps->first()->getName(), $privileges);
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

        /** @var EntityRepositoryInterface $aclRoleRepository */
        $aclRoleRepository = $this->getContainer()->get('acl_role.repository');
        $aclRole = $aclRoleRepository->search(new Criteria([$aclRoleId]), $this->context)->first();

        static::assertNotContains($appPrivilege, $aclRole->getPrivileges());
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

        $privileges = $connection->fetchColumn('
            SELECT `privileges`
            FROM `acl_role`
            WHERE `id` = :aclRoleId
        ', ['aclRoleId' => Uuid::fromHexToBytes($roleId)]);

        $privileges = json_decode($privileges, true);

        static::assertCount(15, $privileges);

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
    }

    private function assertDefaultCustomFields(string $appId): void
    {
        /** @var EntityRepositoryInterface $customFieldSetRepository */
        $customFieldSetRepository = $this->getContainer()->get('custom_field_set.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addAssociation('relations');

        /** @var CustomFieldSetCollection $customFieldSets */
        $customFieldSets = $customFieldSetRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(1, $customFieldSets);

        $customFieldSet = $customFieldSets->first();
        static::assertEquals('custom_field_test', $customFieldSet->getName());
        static::assertCount(2, $customFieldSet->getRelations());

        $relatedEntities = array_map(function (CustomFieldSetRelationEntity $relation) {
            return $relation->getEntityName();
        }, $customFieldSet->getRelations()->getElements());
        static::assertContains('product', $relatedEntities);
        static::assertContains('customer', $relatedEntities);

        static::assertEquals([
            'label' => [
                'en-GB' => 'Custom field test',
                'de-DE' => 'Zusatzfeld Test',
            ],
            'translated' => true,
        ], $customFieldSet->getConfig());
    }

    private function assertDefaultWebhooks(string $appId): void
    {
        /** @var EntityRepositoryInterface $webhookRepository */
        $webhookRepository = $this->getContainer()->get('webhook.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        $webhooks = $webhookRepository->search($criteria, $this->context)->getElements();

        static::assertCount(2, $webhooks);

        usort($webhooks, static function (WebhookEntity $a, WebhookEntity $b): int {
            return $a->getUrl() <=> $b->getUrl();
        });

        /** @var WebhookEntity $firstWebhook */
        $firstWebhook = $webhooks[0];
        static::assertEquals('https://test.com/hook', $firstWebhook->getUrl());
        static::assertEquals('checkout.customer.before.login', $firstWebhook->getEventName());

        /** @var WebhookEntity $secondWebhook */
        $secondWebhook = $webhooks[1];
        static::assertEquals('https://test.com/hook2', $secondWebhook->getUrl());
        static::assertEquals('checkout.order.placed', $secondWebhook->getEventName());
    }

    private function assertDefaultTemplate(string $appId, bool $active = true): void
    {
        /** @var EntityRepositoryInterface $templateRepository */
        $templateRepository = $this->getContainer()->get('app_template.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addSorting(new FieldSorting('path', FieldSorting::ASCENDING));
        $templates = $templateRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(2, $templates);
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

    private function assertDefaultPaymentMethods(string $appId): void
    {
        /** @var EntityRepositoryInterface $paymentMethodRepository */
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
        if (!Feature::isActive('FEATURE_NEXT_14408')) {
            return;
        }

        /** @var EntityRepositoryInterface $cmsBlockRepository */
        $cmsBlockRepository = $this->getContainer()->get('app_cms_block.repository');

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('appId', $appId)
        );

        $cmsBlocks = $cmsBlockRepository->search($criteria, $this->context)->getEntities();
        static::assertCount(2, $cmsBlocks);

        /** @var AppCmsBlockEntity|null $firstCmsBlock */
        $firstCmsBlock = $cmsBlocks->filterByProperty('name', 'my-first-block')->first();
        static::assertEquals('my-first-block', $firstCmsBlock->getName());
        static::assertEquals('First block from app', $firstCmsBlock->getLabel());
        static::assertJsonStringEqualsJsonFile(
            __DIR__ . '/_fixtures/cms/expectedFirstCmsBlock.json',
            json_encode($firstCmsBlock->getBlock())
        );
        static::assertEquals(
            file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/Resources/cms/blocks/my-first-block/preview.html'),
            $firstCmsBlock->getTemplate()
        );
        static::assertEquals(
            file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/Resources/cms/blocks/my-first-block/styles.css'),
            $firstCmsBlock->getStyles()
        );

        /** @var AppCmsBlockEntity|null $secondCmsBlock */
        $secondCmsBlock = $cmsBlocks->filterByProperty('name', 'my-second-block')->first();
        static::assertEquals('my-second-block', $secondCmsBlock->getName());
        static::assertEquals('Second block from app', $secondCmsBlock->getLabel());
        static::assertJsonStringEqualsJsonFile(
            __DIR__ . '/_fixtures/cms/expectedSecondCmsBlock.json',
            json_encode($secondCmsBlock->getBlock())
        );
        static::assertEquals(
            file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/Resources/cms/blocks/my-second-block/preview.html'),
            $secondCmsBlock->getTemplate()
        );
        static::assertEquals(
            file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/Resources/cms/blocks/my-second-block/styles.css'),
            $secondCmsBlock->getStyles()
        );
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

    private function getIsoId(string $iso)
    {
        /** @var EntityRepository $localeRepository */
        $localeRepository = $this->getContainer()->get('locale.repository');

        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('code', $iso));

        $isoId = $localeRepository->search($criteria, Context::createDefaultContext())->first()->getId();

        return $isoId;
    }

    private function createUser($userId): void
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

    private function createAclRole($aclRoleId, $privileges): void
    {
        $this->getContainer()->get(Connection::class)->insert('acl_role', [
            'id' => Uuid::fromHexToBytes($aclRoleId),
            'name' => 'aclTest',
            'privileges' => json_encode($privileges),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createAclUserRole($userId, $aclRoleId): void
    {
        $this->getContainer()->get(Connection::class)->insert('acl_user_role', [
            'user_id' => Uuid::fromHexToBytes($userId),
            'acl_role_id' => Uuid::fromHexToBytes($aclRoleId),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
