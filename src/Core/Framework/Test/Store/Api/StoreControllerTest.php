<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Store\Api\StoreController;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;

class StoreControllerTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * This is a regression test for NEXT-12957. It ensures, that the downloadPlugin method of the StoreController does
     * not dispatch a call to the PluginLifecycleService::updatePlugin method.
     *
     * @see https://issues.shopware.com/issues/NEXT-12957
     */
    public function testDownloadPluginUpdateBehaviour(): void
    {
        $pluginLifecycleService = $this->getPluginLifecycleServiceMock();
        $pluginLifecycleService->expects(static::never())->method('updatePlugin');

        $storeController = $this->getStoreController();

        $storeController->downloadPlugin(
            new QueryDataBag([
                'unauthenticated' => true,
                'language' => 'not-null',
                'pluginName' => 'not-null',
            ]),
            Context::createDefaultContext()
        );
    }

    private function getStoreController(
        ?StoreClient $storeClient = null,
        ?EntityRepositoryInterface $pluginRepo = null,
        ?PluginManagementService $pluginManagementService = null
    ): StoreController {
        return new StoreController(
            $storeClient ?? $this->getStoreClientMock(),
            $pluginRepo ?? $this->getPluginRepositoryMock(),
            $pluginManagementService ?? $this->getPluginManagementServiceMock(),
            $this->getContainer()->get('user.repository'),
            null
        );
    }

    private function getStoreClientMock()
    {
        $storeClient = $this->getMockBuilder(StoreClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDownloadDataForPlugin'])
            ->getMock();

        $storeClient->method('getDownloadDataForPlugin')
            ->willReturn($this->getPluginDownloadDataStub());

        return $storeClient;
    }

    private function getPluginRepositoryMock()
    {
        $pluginRepository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['search'])
            ->getMock();

        $pluginRepository->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'plugin',
                    1,
                    new EntityCollection([
                        $this->getPluginStub(),
                    ]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );

        return $pluginRepository;
    }

    private function getPluginManagementServiceMock()
    {
        $pluginManagementService = $this->getMockBuilder(PluginManagementService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['downloadStorePlugin'])
            ->getMock();

        $pluginManagementService->expects(static::once())
            ->method('downloadStorePlugin');

        return $pluginManagementService;
    }

    private function getPluginLifecycleServiceMock()
    {
        return $this->getMockBuilder(PluginLifecycleService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['updatePlugin'])
            ->getMock();
    }

    private function getPluginStub(): PluginEntity
    {
        $plugin = new PluginEntity();

        $plugin->setId('0f4384bc2d884f519bd3627c3d91d539');
        $plugin->setUpgradeVersion('not-null');
        $plugin->setManagedByComposer(false);

        return $plugin;
    }

    private function getPluginDownloadDataStub(): PluginDownloadDataStruct
    {
        return (new PluginDownloadDataStruct())
            ->assign([
                'location' => 'not-null',
            ]);
    }
}
