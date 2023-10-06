<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\Subscriber\PluginAclPrivilegesSubscriber;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class PluginAclTest extends TestCase
{
    private const PLUGINS_NAMESPACE = 'SwagTestPluginAcl';

    private const PLUGIN_ACL_PRODUCT_VIEWER = 'SwagTestPluginAclProductViewer';
    private const PLUGIN_ACL_PRODUCT_WRITER = 'SwagTestPluginAclProductWriter';
    private const PLUGIN_ACL_PRODUCT_VIEWER_ADDITIONAL = 'SwagTestPluginAclAdditionalProductViewer';
    private const PLUGIN_ACL_OPEN_TO_ALL = 'SwagTestPluginAclOpenToAllRead';

    private const PLUGINS_TO_LOAD = [
        self::PLUGIN_ACL_PRODUCT_VIEWER,
        self::PLUGIN_ACL_PRODUCT_WRITER,
        self::PLUGIN_ACL_PRODUCT_VIEWER_ADDITIONAL,
        self::PLUGIN_ACL_OPEN_TO_ALL,
    ];

    /**
     * @var Plugin[]
     */
    private array $plugins = [];

    /**
     * @var string
     */
    private $testPluginBaseDir;

    private PluginAclPrivilegesSubscriber $pluginAclSubscriber;

    protected function setUp(): void
    {
        $this->testPluginBaseDir = __DIR__ . '/_fixture/plugins/' . self::PLUGINS_NAMESPACE;

        foreach (self::PLUGINS_TO_LOAD as $pluginToLoad) {
            require_once $this->testPluginBaseDir . '/src/' . $pluginToLoad . '.php';
        }

        $pluginCollection = $this->createMock(KernelPluginCollection::class);

        $pluginCollection
            ->method('getActives')
            ->willReturnCallback(fn () => array_filter($this->plugins, static fn (Plugin $plugin) => $plugin->isActive()));

        $this->pluginAclSubscriber = new PluginAclPrivilegesSubscriber($pluginCollection);
    }

    public function testAclPluginDeactivated(): void
    {
        $this->deactivatePlugin(self::PLUGIN_ACL_PRODUCT_VIEWER);

        $aclRoles = [$this->getAclRoleMock('pluginAclTestProductViewer', ['product.viewer', 'product:read'])];

        $event = new EntityLoadedEvent(
            $this->createMock(AclRoleDefinition::class),
            $aclRoles,
            Context::createDefaultContext()
        );

        $this->pluginAclSubscriber->onAclRoleLoaded($event);

        /** @var AclRoleEntity $enrichedAclRole */
        $enrichedAclRole = $event->getEntities()[0];

        static::assertSame(['product.viewer', 'product:read'], $enrichedAclRole->getPrivileges());
    }

    public function testAclPluginActivated(): void
    {
        $this->activatePlugin(self::PLUGIN_ACL_PRODUCT_VIEWER);

        $aclRoles = [$this->getAclRoleMock('pluginAclTestProductViewer', ['product.viewer', 'product:read'])];

        $event = new EntityLoadedEvent(
            $this->createMock(AclRoleDefinition::class),
            $aclRoles,
            Context::createDefaultContext()
        );

        $this->pluginAclSubscriber->onAclRoleLoaded($event);

        /** @var AclRoleEntity $enrichedAclRole */
        $enrichedAclRole = $event->getEntities()[0];

        static::assertSame(['product.viewer', 'product:read', 'swag_demo_data:read'], $enrichedAclRole->getPrivileges());
    }

    public function testAclPluginSubscriberAssociativeArray(): void
    {
        $this->activatePlugin(self::PLUGIN_ACL_PRODUCT_VIEWER);

        $aclRoles = [$this->getAclRoleMock('pluginAclTestProductViewer', ['product.viewer', 'product:read'])];

        $event = new EntityLoadedEvent(
            $this->createMock(AclRoleDefinition::class),
            $aclRoles,
            Context::createDefaultContext()
        );

        $this->pluginAclSubscriber->onAclRoleLoaded($event);

        /** @var AclRoleEntity $enrichedAclRole */
        $enrichedAclRole = $event->getEntities()[0];

        static::assertSame($enrichedAclRole->getPrivileges(), array_values($enrichedAclRole->getPrivileges()));
    }

    public function testAclPluginOpenToAllDeactivated(): void
    {
        $this->deactivatePlugin(self::PLUGIN_ACL_OPEN_TO_ALL);

        $aclRoles = [$this->getAclRoleMock('pluginAclTestProductViewer', ['product.viewer', 'product:read'])];

        $event = new EntityLoadedEvent(
            $this->createMock(AclRoleDefinition::class),
            $aclRoles,
            Context::createDefaultContext()
        );

        $this->pluginAclSubscriber->onAclRoleLoaded($event);

        /** @var AclRoleEntity $enrichedAclRole */
        $enrichedAclRole = $event->getEntities()[0];

        static::assertSame(['product.viewer', 'product:read'], $enrichedAclRole->getPrivileges());
    }

    public function testAclPluginOpenToAllActivated(): void
    {
        $this->activatePlugin(self::PLUGIN_ACL_OPEN_TO_ALL);

        $aclRoles = [$this->getAclRoleMock('pluginAclTestProductViewer', ['product.viewer', 'product:read'])];

        $event = new EntityLoadedEvent(
            $this->createMock(AclRoleDefinition::class),
            $aclRoles,
            Context::createDefaultContext()
        );

        $this->pluginAclSubscriber->onAclRoleLoaded($event);

        /** @var AclRoleEntity $enrichedAclRole */
        $enrichedAclRole = $event->getEntities()[0];

        static::assertSame(['product.viewer', 'product:read', 'open_to_all:read'], $enrichedAclRole->getPrivileges());
    }

    public function testAclPluginOtherRolesUnaffected(): void
    {
        $this->activatePlugin(self::PLUGIN_ACL_PRODUCT_VIEWER);

        $aclRoles = [
            $this->getAclRoleMock('pluginAclTestProductViewer', ['product.viewer', 'product:read']),
            $this->getAclRoleMock('pluginAclTestProductWriter', ['product.writer', 'product:write']),
        ];

        $event = new EntityLoadedEvent(
            $this->createMock(AclRoleDefinition::class),
            $aclRoles,
            Context::createDefaultContext()
        );

        $this->pluginAclSubscriber->onAclRoleLoaded($event);

        /** @var AclRoleEntity $enrichedAclRole */
        $enrichedAclRole = $event->getEntities()[1];

        static::assertSame(['product.writer', 'product:write'], $enrichedAclRole->getPrivileges());
    }

    public function testAclPluginCycle(): void
    {
        $this->activatePlugin(self::PLUGIN_ACL_PRODUCT_VIEWER);

        $aclRoles = [
            $this->getAclRoleMock('pluginAclTestProductViewer', ['product.viewer', 'product:read']),
            $this->getAclRoleMock('pluginAclTestProductWriter', ['product.writer', 'product:write']),
        ];

        $event = new EntityLoadedEvent(
            $this->createMock(AclRoleDefinition::class),
            $aclRoles,
            Context::createDefaultContext()
        );

        $this->pluginAclSubscriber->onAclRoleLoaded($event);

        /** @var AclRoleEntity $enrichedAclRole */
        $enrichedAclRole = $event->getEntities()[0];

        static::assertSame(['product.viewer', 'product:read', 'swag_demo_data:read'], $enrichedAclRole->getPrivileges());

        $this->deactivatePlugin(self::PLUGIN_ACL_PRODUCT_VIEWER);

        $aclRoles = [
            $this->getAclRoleMock('pluginAclTestProductViewer', ['product.viewer', 'product:read']),
            $this->getAclRoleMock('pluginAclTestProductWriter', ['product.writer', 'product:write']),
        ];

        $event = new EntityLoadedEvent(
            $this->createMock(AclRoleDefinition::class),
            $aclRoles,
            Context::createDefaultContext()
        );

        $this->pluginAclSubscriber->onAclRoleLoaded($event);

        /** @var AclRoleEntity $enrichedAclRole */
        $enrichedAclRole = $event->getEntities()[0];

        static::assertSame(['product.viewer', 'product:read'], $enrichedAclRole->getPrivileges());
    }

    public function testAclPluginsMultipleActivated(): void
    {
        $this->activatePlugin(self::PLUGIN_ACL_PRODUCT_VIEWER);
        $this->activatePlugin(self::PLUGIN_ACL_PRODUCT_WRITER);

        $aclRoles = [
            $this->getAclRoleMock('pluginAclTestProductViewer', ['product.viewer', 'product:read']),
            $this->getAclRoleMock('pluginAclTestProductWriter', ['product.writer', 'product:write']),
        ];

        $event = new EntityLoadedEvent(
            $this->createMock(AclRoleDefinition::class),
            $aclRoles,
            Context::createDefaultContext()
        );

        $this->pluginAclSubscriber->onAclRoleLoaded($event);

        /** @var AclRoleEntity $enrichedReaderRole */
        $enrichedReaderRole = $event->getEntities()[0];

        /** @var AclRoleEntity $enrichedWriterRole */
        $enrichedWriterRole = $event->getEntities()[1];

        static::assertSame(['product.viewer', 'product:read', 'swag_demo_data:read'], $enrichedReaderRole->getPrivileges());
        static::assertSame(['product.writer', 'product:write', 'swag_demo_data:write'], $enrichedWriterRole->getPrivileges());
    }

    public function testAclPluginsMultiplePluginsSamePrivileges(): void
    {
        $this->activatePlugin(self::PLUGIN_ACL_PRODUCT_VIEWER);
        $this->activatePlugin(self::PLUGIN_ACL_PRODUCT_VIEWER_ADDITIONAL);

        $aclRoles = [$this->getAclRoleMock('pluginAclTestProductViewer', ['product.viewer', 'product:read'])];

        $event = new EntityLoadedEvent(
            $this->createMock(AclRoleDefinition::class),
            $aclRoles,
            Context::createDefaultContext()
        );

        $this->pluginAclSubscriber->onAclRoleLoaded($event);

        /** @var AclRoleEntity $enrichedRole */
        $enrichedRole = $event->getEntities()[0];

        static::assertSame(['product.viewer', 'product:read', 'swag_demo_data:read'], $enrichedRole->getPrivileges());
    }

    public function testAclPluginsMultiplePluginsSamePrivilegesOneDeactivated(): void
    {
        $this->activatePlugin(self::PLUGIN_ACL_PRODUCT_VIEWER);
        $this->deactivatePlugin(self::PLUGIN_ACL_PRODUCT_VIEWER_ADDITIONAL);

        $aclRoles = [$this->getAclRoleMock('pluginAclTestProductViewer', ['product.viewer', 'product:read'])];

        $event = new EntityLoadedEvent(
            $this->createMock(AclRoleDefinition::class),
            $aclRoles,
            Context::createDefaultContext()
        );

        $this->pluginAclSubscriber->onAclRoleLoaded($event);

        /** @var AclRoleEntity $enrichedRole */
        $enrichedRole = $event->getEntities()[0];

        static::assertSame(['product.viewer', 'product:read', 'swag_demo_data:read'], $enrichedRole->getPrivileges());
    }

    private function getAclRoleMock(string $name, array $privileges): AclRoleEntity
    {
        return (new AclRoleEntity())->assign(
            [
                'id' => Uuid::randomHex(),
                'name' => $name,
                'privileges' => $privileges,
            ]
        );
    }

    private function activatePlugin(string $pluginName): void
    {
        $class = '\\' . self::PLUGINS_NAMESPACE . '\\' . $pluginName;
        $this->plugins[$pluginName] = new $class(true, $this->testPluginBaseDir);
    }

    private function deactivatePlugin(string $pluginName): void
    {
        $class = '\\' . self::PLUGINS_NAMESPACE . '\\' . $pluginName;
        $this->plugins[$pluginName] = new $class(false, $this->testPluginBaseDir);
    }
}
