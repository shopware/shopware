<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Page\LandingPage\LandingPageLoadedHook;

/**
 * @internal
 */
#[Package('content')]
class LandingPageControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->createData();
    }

    public function testLandingPageLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/landingPage/' . $this->ids->get('landing-page'), []);
        static::assertEquals(200, $response->getStatusCode());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(LandingPageLoadedHook::HOOK_NAME, $traces);
    }

    private function createData(): void
    {
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->getContainer()->get('sales_channel.repository')->search(
            (
                new Criteria())->addFilter(
                    new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                    new EqualsFilter('domains.url', $_SERVER['APP_URL'])
                ),
            Context::createDefaultContext()
        )->first();

        $data = [
            'id' => $this->ids->create('landing-page'),
            'name' => 'Test',
            'url' => 'myUrl',
            'active' => true,
            'salesChannels' => [
                [
                    'id' => $salesChannel->getId(),
                ],
            ],
            'cmsPage' => [
                'id' => $this->ids->create('cms-page'),
                'type' => 'product_list',
                'sections' => [
                    [
                        'position' => 0,
                        'type' => 'sidebar',
                        'blocks' => [
                            [
                                'type' => 'product-listing',
                                'position' => 1,
                                'slots' => [
                                    ['type' => 'product-listing', 'slot' => 'content'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->getContainer()->get('landing_page.repository')
            ->create([$data], Context::createDefaultContext());
    }
}
