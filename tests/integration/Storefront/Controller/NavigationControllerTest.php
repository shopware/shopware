<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedHook;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;

/**
 * @internal
 */
class NavigationControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->createData();
    }

    public function testNavigationPageLoadedHookScriptsAreExecutedForCategory(): void
    {
        $response = $this->request('GET', '/my-navigation/', []);

        static::assertEquals(200, $response->getStatusCode(), print_r($response->getContent(), true));

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(NavigationPageLoadedHook::HOOK_NAME, $traces);
    }

    private function createData(): void
    {
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->getContainer()->get('sales_channel.repository')->search(
            (new Criteria())->addFilter(
                new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                new EqualsFilter('domains.url', $_SERVER['APP_URL'])
            ),
            Context::createDefaultContext()
        )->first();

        $category = [
            'id' => $this->ids->create('category'),
            'name' => 'my-navigation',
            'type' => 'landing_page',
            'parentId' => $salesChannel->getNavigationCategoryId(),
        ];

        $this->getContainer()->get('category.repository')->create([$category], Context::createDefaultContext());
    }
}
