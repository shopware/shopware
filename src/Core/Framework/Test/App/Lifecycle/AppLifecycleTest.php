<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Lifecycle;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonEntity;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\Persister\PermissionPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Permissions;
use Shopware\Core\Framework\App\Template\TemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\WebhookEntity;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AppLifecycleTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    /**
     * @var AppLifecycle
     */
    private $appLifecycle;

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var EntityRepositoryInterface
     */
    private $actionButtonRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10286', $this);
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->actionButtonRepository = $this->getContainer()->get('app_action_button.repository');

        $this->appLifecycle = $this->getContainer()->get(AppLifecycle::class);
        $this->context = Context::createDefaultContext();

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
            static::assertEquals($this->context, $event->getContext());
            static::assertEquals($manifest, $event->getManifest());
        };
        $this->eventDispatcher->addListener(AppInstalledEvent::class, $onAppInstalled);

        $this->appLifecycle->install($manifest, true, $this->context);

        static::assertTrue($eventWasReceived);
        $this->eventDispatcher->removeListener(AppInstalledEvent::class, $onAppInstalled);
        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('SwagApp', $apps->first()->getName());
        static::assertEquals(
            base64_encode(file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/icon.png')),
            $apps->first()->getIcon()
        );

        static::assertEquals($appId, $apps->first()->getId());
        $this->assertDefaultActionButtons();
        $this->assertDefaultModules($apps->first());
        $this->assertDefaultPrivileges($apps->first()->getAclRoleId());
        $this->assertDefaultCustomFields($apps->first()->getId());
        $this->assertDefaultWebhooks($apps->first()->getId());
        $this->assertDefaultTemplate($apps->first()->getId());
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
        static::assertEquals('SwagAppMinimal', $apps->first()->getName());
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

    public function testUpdateInactiveApp(): void
    {
        $id = Uuid::randomHex();
        $roleId = Uuid::randomHex();

        $this->appRepository->create([[
            'id' => $id,
            'name' => 'SwagApp',
            'path' => __DIR__ . '/../Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
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
                'name' => 'SwagApp',
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
        ]], $this->context);

        /** @var PermissionPersister $permissionPersister */
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
            static::assertEquals($this->context, $event->getContext());
            static::assertEquals($manifest, $event->getManifest());
        };
        $this->eventDispatcher->addListener(AppUpdatedEvent::class, $onAppUpdated);

        $this->appLifecycle->update($manifest, $app, $this->context);

        static::assertTrue($eventWasReceived);
        $this->eventDispatcher->removeListener(AppUpdatedEvent::class, $onAppUpdated);
        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('SwagApp', $apps->first()->getName());
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
    }

    public function testUpdateActiveApp(): void
    {
        $id = Uuid::randomHex();
        $roleId = Uuid::randomHex();

        $this->appRepository->create([[
            'id' => $id,
            'name' => 'SwagApp',
            'path' => __DIR__ . '/../Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
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
                'name' => 'SwagApp',
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
        ]], $this->context);

        /** @var PermissionPersister $permissionPersister */
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
            static::assertEquals($this->context, $event->getContext());
            static::assertEquals($manifest, $event->getManifest());
        };
        $this->eventDispatcher->addListener(AppUpdatedEvent::class, $onAppUpdated);

        $this->appLifecycle->update($manifest, $app, $this->context);

        static::assertTrue($eventWasReceived);
        $this->eventDispatcher->removeListener(AppUpdatedEvent::class, $onAppUpdated);
        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('SwagApp', $apps->first()->getName());
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
    }

    public function testUpdateDoesNotInstallElementsNeedingAppSecretIfItIsMissing(): void
    {
        $id = Uuid::randomHex();
        $roleId = Uuid::randomHex();

        $this->appRepository->create([[
            'id' => $id,
            'name' => 'SwagApp',
            'path' => __DIR__ . '/../Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
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
                'name' => 'SwagApp',
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
        ]], $this->context);

        /** @var PermissionPersister $permissionPersister */
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

    public function testDelete(): void
    {
        $appId = Uuid::randomHex();
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
                'name' => 'SwagApp',
            ],
        ]], $this->context);

        $app = [
            'id' => $appId,
        ];

        $eventWasReceived = false;
        $onAppDeleted = function (AppDeletedEvent $event) use (&$eventWasReceived, $appId): void {
            $eventWasReceived = true;
            static::assertEquals($appId, $event->getAppId());
            static::assertEquals($this->context, $event->getContext());
        };
        $this->eventDispatcher->addListener(AppDeletedEvent::class, $onAppDeleted);

        $this->appLifecycle->delete('Test', $app, $this->context);

        static::assertTrue($eventWasReceived);
        $this->eventDispatcher->removeListener(AppDeletedEvent::class, $onAppDeleted);
        $apps = $this->appRepository->searchIds(new Criteria([$appId]), $this->context)->getIds();
        static::assertCount(0, $apps);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $apps = $this->actionButtonRepository->searchIds($criteria, $this->context)->getIds();
        static::assertCount(0, $apps);
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
        static::assertCount(1, $app->getModules());

        static::assertEquals([
            'label' => [
                'en-GB' => 'My first own module',
                'de-DE' => 'Mein erstes eigenes Modul',
            ],
            'source' => 'https://test.com',
            'name' => 'first-module',
        ], $app->getModules()[0]);
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

        static::assertCount(14, $privileges);

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

    private function assertDefaultTemplate(string $appId): void
    {
        /** @var EntityRepositoryInterface $templateRepository */
        $templateRepository = $this->getContainer()->get('app_template.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        $templates = $templateRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(1, $templates);

        /** @var TemplateEntity $template */
        $template = $templates->first();
        static::assertEquals('storefront/layout/header/logo.html.twig', $template->getPath());
        static::assertStringEqualsFile(
            __DIR__ . '/../Manifest/_fixtures/test/Resources/views/storefront/layout/header/logo.html.twig',
            $template->getTemplate()
        );
        static::assertTrue($template->isActive());
    }
}
