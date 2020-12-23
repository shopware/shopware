<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use Composer\IO\NullIO;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Plugin\Composer\CommandExecutor;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/** @deprecated tag:v6.4.0.0 */
class PluginAclTestDeprecated extends TestCase
{
    use PluginTestsHelper;
    use KernelTestBehaviour;

    private const PLUGIN_NAME = 'SwagTestPluginAcl';

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var PluginService
     */
    private $pluginService;

    /**
     * @var KernelPluginCollection
     */
    private $pluginCollection;

    /**
     * @var PluginLifecycleService
     */
    private $pluginLifecycleService;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    protected function setUp(): void
    {
        // force kernel boot
        KernelLifecycleManager::bootKernel();

        $this->getContainer()
            ->get(Connection::class)
            ->beginTransaction();

        $this->container = $this->getContainer();
        $this->pluginRepo = $this->container->get('plugin.repository');
        $this->pluginService = $this->createPluginService(
            $this->pluginRepo,
            $this->container->get('language.repository'),
            $this->container->getParameter('kernel.project_dir'),
            $this->container->get(PluginFinder::class)
        );
        $this->pluginCollection = $this->container->get(KernelPluginCollection::class);
        $this->systemConfigService = $this->container->get(SystemConfigService::class);
        $this->pluginLifecycleService = $this->createPluginLifecycleService();

        $this->addTestPluginToKernel(self::PLUGIN_NAME);

        $this->context = Context::createDefaultContext();
    }

    protected function tearDown(): void
    {
        $this->getContainer()
            ->get(Connection::class)
            ->rollBack();
    }

    public function testAclPluginNoRoles(): void
    {
        $pluginActivated = $this->installAndActivatePlugin($this->context);

        static::assertTrue($pluginActivated->getActive());

        /** @var EntityRepositoryInterface $aclRoleRepository */
        $aclRoleRepository = $this->container->get('acl_role.repository');

        $aclRole = null;
        $this->context->disableCache(function ($context) use ($aclRoleRepository, &$aclRole): void {
            /** @var AclRoleEntity $aclRole */
            $aclRole = $aclRoleRepository->search(new Criteria([]), $context)->first();
        });

        static::assertNull($aclRole);

        $this->pluginLifecycleService->deactivatePlugin($pluginActivated, $this->context);

        $pluginDeactivated = $this->getTestPlugin($this->context);

        static::assertFalse($pluginDeactivated->getActive());

        $aclRole = null;
        $this->context->disableCache(function ($context) use ($aclRoleRepository, &$aclRole): void {
            /** @var AclRoleEntity $aclRole */
            $aclRole = $aclRoleRepository->search(new Criteria([]), $context)->first();
        });
        static::assertNull($aclRole);

        $filesystem = $this->container->get(Filesystem::class);
        $filesystem->remove(__DIR__ . '/public');
    }

    public function testAclPluginMatchingRule(): void
    {
        $roleIdReader = Uuid::randomHex();
        $roleIdWriter = Uuid::randomHex();
        /** @var EntityRepositoryInterface $aclRoleRepository */
        $aclRoleRepository = $this->container->get('acl_role.repository');
        $aclRoleRepository->create(
            [
                [
                    'id' => $roleIdReader,
                    'name' => 'pluginAclTestProductViewer',
                    'privileges' => [
                        'product.viewer',
                        'product:read',
                    ],
                ],
                [
                    'id' => $roleIdWriter,
                    'name' => 'pluginAclTestProductWriter',
                    'privileges' => [
                        'product.editor',
                        'product:write',
                    ],
                ],
            ],
            $this->context
        );

        $pluginActivated = $this->installAndActivatePlugin($this->context);

        $aclRole = null;
        $this->context->disableCache(function ($context) use ($aclRoleRepository, $roleIdReader, &$aclRole): void {
            /** @var AclRoleEntity $aclRole */
            $aclRole = $aclRoleRepository->search(new Criteria([$roleIdReader]), $context)->first();
        });

        static::assertEquals(
            [
                'product.viewer',
                'product:read',
                'swag_demo_data:read',
            ],
            $aclRole->getPrivileges()
        );

        $this->pluginLifecycleService->deactivatePlugin($pluginActivated, $this->context);

        $pluginDeactivated = $this->getTestPlugin($this->context);

        static::assertFalse($pluginDeactivated->getActive());

        $aclRole = null;
        $this->context->disableCache(function ($context) use ($aclRoleRepository, $roleIdReader, &$aclRole): void {
            /** @var AclRoleEntity $aclRole */
            $aclRole = $aclRoleRepository->search(new Criteria([$roleIdReader]), $context)->first();
        });
        static::assertEquals(
            [
                'product.viewer',
                'product:read',
            ],
            $aclRole->getPrivileges()
        );

        $filesystem = $this->container->get(Filesystem::class);
        $filesystem->remove(__DIR__ . '/public');
    }

    public function testAclPluginNoMatchingRule(): void
    {
        $roleIdSomething = Uuid::randomHex();
        $roleIdWriter = Uuid::randomHex();
        /** @var EntityRepositoryInterface $aclRoleRepository */
        $aclRoleRepository = $this->container->get('acl_role.repository');
        $aclRoleRepository->create(
            [
                [
                    'id' => $roleIdSomething,
                    'name' => 'pluginAclTestProductViewer',
                    'privileges' => [
                        'product.something',
                        'product:read',
                    ],
                ],
                [
                    'id' => $roleIdWriter,
                    'name' => 'pluginAclTestProductWriter',
                    'privileges' => [
                        'product.editor',
                        'product:write',
                    ],
                ],
            ],
            $this->context
        );

        $pluginActivated = $this->installAndActivatePlugin($this->context);

        $aclRole = null;
        $this->context->disableCache(function ($context) use ($aclRoleRepository, $roleIdSomething, &$aclRole): void {
            /** @var AclRoleEntity $aclRole */
            $aclRole = $aclRoleRepository->search(new Criteria([$roleIdSomething]), $context)->first();
        });

        static::assertEquals(
            [
                'product.something',
                'product:read',
            ],
            $aclRole->getPrivileges()
        );

        $this->pluginLifecycleService->deactivatePlugin($pluginActivated, $this->context);

        $pluginDeactivated = $this->getTestPlugin($this->context);

        static::assertFalse($pluginDeactivated->getActive());

        $aclRole = null;
        $this->context->disableCache(function ($context) use ($aclRoleRepository, $roleIdSomething, &$aclRole): void {
            /** @var AclRoleEntity $aclRole */
            $aclRole = $aclRoleRepository->search(new Criteria([$roleIdSomething]), $context)->first();
        });

        static::assertEquals(
            [
                'product.something',
                'product:read',
            ],
            $aclRole->getPrivileges()
        );

        $filesystem = $this->container->get(Filesystem::class);
        $filesystem->remove(__DIR__ . '/public');
    }

    private function createPluginLifecycleService(): PluginLifecycleService
    {
        return new PluginLifecycleService(
            $this->pluginRepo,
            $this->container->get('event_dispatcher'),
            $this->pluginCollection,
            $this->container->get('service_container'),
            $this->container->get(MigrationCollectionLoader::class),
            $this->container->get(AssetService::class),
            $this->container->get(CommandExecutor::class),
            $this->container->get(RequirementsValidator::class),
            $this->container->get('cache.messenger.restart_workers_signal'),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->systemConfigService
        );
    }

    private function installPlugin(Context $context): PluginEntity
    {
        $plugin = $this->getPlugin($context);

        $this->pluginLifecycleService->installPlugin($plugin, $context);

        return $this->getTestPlugin($context);
    }

    private function installAndActivatePlugin(Context $context): PluginEntity
    {
        $pluginInstalled = $this->installPlugin($context);
        static::assertNotNull($pluginInstalled->getInstalledAt());

        $this->pluginLifecycleService->activatePlugin($pluginInstalled, $context);
        $pluginActivated = $this->getTestPlugin($context);
        static::assertTrue($pluginActivated->getActive());

        return $pluginActivated;
    }

    private function getPlugin(Context $context): PluginEntity
    {
        $this->pluginService->refreshPlugins($context, new NullIO());

        return $this->getTestPlugin($context);
    }

    private function getTestPlugin(Context $context): PluginEntity
    {
        return $this->pluginService->getPluginByName(self::PLUGIN_NAME, $context);
    }
}
