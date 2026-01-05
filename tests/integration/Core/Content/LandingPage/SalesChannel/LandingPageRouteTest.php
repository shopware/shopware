<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\LandingPage\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
#[Package('discovery')]
class LandingPageRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->createData();
    }

    public function testGetLandingPage(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/landing-page/' . $this->ids->get('landing-page')
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame($this->ids->get('landing-page'), $response['id']);
        static::assertSame('My landing page', $response['name']);
        static::assertArrayHasKey('cmsPage', $response);
        static::assertSame($this->ids->get('cms-page'), $response['cmsPage']['id']);
    }

    public function testPostLandingPage(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/landing-page/' . $this->ids->get('landing-page')
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame($this->ids->get('landing-page'), $response['id']);
        static::assertArrayHasKey('cmsPage', $response);
    }

    private function createData(): void
    {
        $context = Context::createDefaultContext();

        /** @var EntityRepository<CmsPageCollection> $cmsPageRepository */
        $cmsPageRepository = static::getContainer()->get('cms_page.repository');
        /** @var EntityRepository<LandingPageCollection> $landingPageRepository */
        $landingPageRepository = static::getContainer()->get('landing_page.repository');

        $cmsPageId = $this->ids->create('cms-page');
        $cmsPageRepository->create([
            [
                'id' => $cmsPageId,
                'name' => 'test page',
                'type' => 'product_list',
                'sections' => [
                    [
                        'id' => $this->ids->create('section'),
                        'type' => 'default',
                        'position' => 0,
                        'blocks' => [
                            [
                                'type' => 'product-listing',
                                'position' => 0,
                                'slots' => [
                                    [
                                        'type' => 'product-listing',
                                        'slot' => 'content',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $context);

        $landingPageRepository->create([
            [
                'id' => $this->ids->create('landing-page'),
                'name' => 'My landing page',
                'url' => 'my-landing-page',
                'active' => true,
                'cmsPageId' => $cmsPageId,
                'salesChannels' => [
                    [
                        'id' => $this->ids->get('sales-channel'),
                    ],
                ],
            ],
        ], $context);
    }
}
