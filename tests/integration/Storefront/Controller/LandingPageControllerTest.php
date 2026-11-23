<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Page\LandingPage\LandingPageLoadedHook;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
class LandingPageControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();
    }

    public function testLandingPageLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/myUrl', []);

        static::assertSame(200, $response->getStatusCode());

        $traces = $this->getStorefrontRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(LandingPageLoadedHook::HOOK_NAME, $traces);
    }

    #[DataProvider('contentLayoutProvider')]
    public function testRendersAssignedContentLayout(bool $withCmsPage, bool $salesChannelAssignment): void
    {
        $context = Context::createDefaultContext();
        $landingPage = [
            'id' => $this->ids->get('landing-page'),
            'name' => 'Assigned landing page',
            'metaTitle' => 'Landing campaign title',
        ];
        if (!$withCmsPage) {
            $landingPage['cmsPageId'] = null;
        }
        static::getContainer()->get('landing_page.repository')->update([$landingPage], $context);
        $this->assignContentLayout($salesChannelAssignment);

        $response = $this->request('GET', '/myUrl', []);
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertStringContainsString('data-page-id="' . $this->ids->get('content-layout') . '"', $content);
        static::assertStringContainsString('data-element-id="' . $this->ids->get('content-text') . '"', $content);
        static::assertStringContainsString('Assigned landing page', $content);
        static::assertStringContainsString('Landing campaign title', $content);

        $traces = $this->getStorefrontRequestContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(LandingPageLoadedHook::HOOK_NAME, $traces);
    }

    /**
     * @return \Generator<string, array{bool, bool}>
     */
    public static function contentLayoutProvider(): \Generator
    {
        yield 'global assignment without a legacy CMS page' => [false, false];
        yield 'content layout takes precedence over legacy CMS' => [true, false];
        yield 'sales channel assignment without a legacy CMS page' => [false, true];
    }

    #[DataProvider('contentRouteParametersProvider')]
    public function testCmsLandingPageIgnoresContentRouteParameters(string $parameter, mixed $value): void
    {
        $response = $this->request('GET', '/myUrl', [$parameter => $value]);
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertStringContainsString('class="cms-page"', $content);
    }

    #[DataProvider('contentRouteParametersProvider')]
    public function testContentLandingPageIgnoresContentRouteParameters(string $parameter, mixed $value): void
    {
        $this->assignContentLayout(salesChannelAssignment: false);

        $response = $this->request('GET', '/myUrl', [$parameter => $value]);
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertStringContainsString('data-element-id="' . $this->ids->get('content-root') . '"', $content);
        static::assertStringContainsString('data-element-id="' . $this->ids->get('content-text') . '"', $content);
    }

    /**
     * @return \Generator<string, array{string, string|array<string, string>}>
     */
    public static function contentRouteParametersProvider(): \Generator
    {
        yield 'scalar includes does not reject the page' => ['includes', '1'];
        yield 'array includes does not reject the page' => ['includes', ['x' => 'y']];
        yield 'array excludes does not reject the page' => ['excludes', ['x' => '1']];
        yield 'unknown element does not reject the page' => ['elementId', 'unknown'];
    }

    public function testContentLandingPageDoesNotRenderOnlyTheRequestedElement(): void
    {
        $this->assignContentLayout(salesChannelAssignment: false);

        $response = $this->request('GET', '/myUrl', ['elementId' => $this->ids->get('content-text')]);
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertStringContainsString('data-element-id="' . $this->ids->get('content-root') . '"', $content);
        static::assertStringContainsString('data-element-id="' . $this->ids->get('content-text') . '"', $content);
    }

    public function testBrokenContentLayoutFallsBackToCmsPage(): void
    {
        $this->assignContentLayout(salesChannelAssignment: false);
        static::getContainer()->get(Connection::class)->update('content_layout', [
            'layout' => '[{}]',
        ], ['id' => Uuid::fromHexToBytes($this->ids->get('content-layout'))]);

        $response = $this->request('GET', '/myUrl', []);
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertStringContainsString('class="cms-page"', $content);
    }

    public function testLandingPageWithoutAnyLayoutIsNotFound(): void
    {
        static::getContainer()->get('landing_page.repository')->update([[
            'id' => $this->ids->get('landing-page'),
            'cmsPageId' => null,
        ]], Context::createDefaultContext());

        $response = $this->request('GET', '/myUrl', []);

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testAssignedLayoutDoesNotExposeInactiveLandingPage(): void
    {
        $this->assignContentLayout(salesChannelAssignment: false);
        static::getContainer()->get('landing_page.repository')->update([[
            'id' => $this->ids->get('landing-page'),
            'active' => false,
            'cmsPageId' => null,
        ]], Context::createDefaultContext());

        $response = $this->request('GET', '/myUrl', []);

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    private function assignContentLayout(bool $salesChannelAssignment): void
    {
        $context = Context::createDefaultContext();
        static::getContainer()->get('content_layout.repository')->create([[
            'id' => $this->ids->create('content-layout'),
            'name' => 'Landing campaign',
            'rootSource' => 'landing_page',
            'version' => '1.0.0',
            'layout' => [[
                'id' => $this->ids->create('content-root'),
                'component' => 'Sw:Grid:Container',
                'properties' => [],
                'slots' => ['content' => [[
                    'id' => $this->ids->create('content-text'),
                    'component' => 'Sw:Content:Text',
                    'properties' => ['text' => [Defaults::LANGUAGE_SYSTEM => '{{map:landing_page.name}}']],
                ]]],
            ]],
        ]], $context);
        static::getContainer()->get('landing_page_content_layout.repository')->create([[
            'id' => $this->ids->create('content-assignment'),
            'landingPageId' => $this->ids->get('landing-page'),
            'salesChannelId' => $salesChannelAssignment ? $this->getSalesChannelId() : null,
            'contentLayoutId' => $this->ids->get('content-layout'),
        ]], $context);
    }

    private function createData(): void
    {
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = static::getContainer()->get('sales_channel.repository')->search(
            (
                new Criteria())->addFilter(
                    new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                    new EqualsFilter('domains.url', $_SERVER['APP_URL'])
                ),
            Context::createDefaultContext()
        )->getEntities()->first();

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

        static::getContainer()->get('landing_page.repository')
            ->create([$data], Context::createDefaultContext());
    }
}
