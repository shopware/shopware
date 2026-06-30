<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Page\Maintenance\MaintenancePageLoadedHook;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;

/**
 * @internal
 */
class MaintenanceControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();
    }

    public function testMaintenancePageLoadedHookScriptsAreExecuted(): void
    {
        $this->setMaintenanceMode();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->followRedirects();

        $browser->request('GET', EnvironmentHelper::getVariable('APP_URL') . '/');
        $response = $browser->getResponse();

        static::assertSame(503, $response->getStatusCode());

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(MaintenancePageLoadedHook::HOOK_NAME, $traces);
    }

    public function testMaintenancePageLoadedHookScriptsAreExecutedForSinglePage(): void
    {
        $response = $this->request('GET', '/maintenance/singlepage/' . $this->ids->get('page'), []);
        static::assertSame(200, $response->getStatusCode());

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(MaintenancePageLoadedHook::HOOK_NAME, $traces);
    }

    public function testMaintenancePageIsRenderedForClientNotInAllowlist(): void
    {
        // a configured allowlist that does not contain the requesting client must still render the maintenance page;
        // this exercises the allowlist IP header handling (DomainLoader -> RequestTransformer -> controller)
        $this->setMaintenanceMode(['10.253.0.1']);

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->followRedirects();
        $browser->request('GET', EnvironmentHelper::getVariable('APP_URL') . '/');
        $response = $browser->getResponse();

        static::assertSame(503, $response->getStatusCode());
    }

    private function createData(): void
    {
        $page = [
            'id' => $this->ids->create('page'),
            'name' => 'test page',
            'type' => 'landingpage',
            'sections' => [
                [
                    'id' => $this->ids->create('section'),
                    'type' => 'default',
                    'position' => 0,
                    'blocks' => [
                        [
                            'type' => 'text',
                            'position' => 0,
                            'slots' => [
                                [
                                    'id' => $this->ids->create('slot1'),
                                    'type' => 'text',
                                    'slot' => 'content',
                                    'config' => [
                                        'content' => [
                                            'source' => 'static',
                                            'value' => 'initial',
                                        ],
                                    ],
                                ],
                                [
                                    'id' => $this->ids->create('slot2'),
                                    'type' => 'text',
                                    'slot' => 'content',
                                    'config' => null,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        static::getContainer()->get('cms_page.repository')->create([$page], Context::createDefaultContext());
    }

    /**
     * @param list<string>|null $allowlist
     */
    private function setMaintenanceMode(?array $allowlist = null): void
    {
        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');

        $salesChannel = $salesChannelRepository->search(
            (new Criteria())->addFilter(
                new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                new EqualsFilter('domains.url', $_SERVER['APP_URL'])
            ),
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertNotNull($salesChannel);

        $update = [
            'id' => $salesChannel->getId(),
            'maintenance' => true,
        ];

        if ($allowlist !== null) {
            $update['maintenanceIpAllowlist'] = $allowlist;
        }

        $salesChannelRepository->update([$update], Context::createDefaultContext());

        static::getContainer()->get(SystemConfigService::class)->set('core.basicInformation.maintenancePage', $this->ids->get('page'));
    }
}
