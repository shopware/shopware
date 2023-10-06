<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Page\Navigation\Error\ErrorPage;
use Shopware\Storefront\Page\Navigation\Error\ErrorPageLoadedEvent;
use Shopware\Storefront\Page\Navigation\Error\ErrorPageLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ErrorPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    private SystemConfigService $systemConfigService;

    private EntityRepository $systemConfigRepository;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    private EntityRepository $cmsPageRepository;

    private string $errorLayoutId;

    protected function setUp(): void
    {
        parent::setUp();

        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $this->systemConfigRepository = $this->getContainer()->get('system_config.repository');
        $this->cmsPageRepository = $this->getContainer()->get('cms_page.repository');
        $this->salesChannelContext = $contextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $this->errorLayoutId = $this->createPage();
        $this->systemConfigService->set('core.basicInformation.http404Page', $this->errorLayoutId);
    }

    public function testItDoesLoad404CmsLayoutPageIn404Case(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();

        /** @var ErrorPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(ErrorPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($this->errorLayoutId, $request, $context);

        static::assertInstanceOf(ErrorPage::class, $page);
        self::assertPageEvent(ErrorPageLoadedEvent::class, $event, $context, $request, $page);
        static::assertSame('404 layout', $page->getCmsPage()->getName());
    }

    protected function getPageLoader()
    {
        return $this->getContainer()->get(ErrorPageLoader::class);
    }

    private function createPage(): string
    {
        $page = [
            'id' => Uuid::randomHex(),
            'name' => '404 layout',
            'type' => 'page',
            'sections' => [
                [
                    'id' => Uuid::randomHex(),
                    'type' => 'default',
                    'position' => 0,
                    'blocks' => [
                        [
                            'position' => 1,
                            'type' => 'image-text',
                            'slots' => [
                                ['type' => 'text', 'slot' => 'left', 'config' => ['content' => ['source' => FieldConfig::SOURCE_STATIC, 'value' => '404 - Not Found']]],
                                ['type' => 'image', 'slot' => 'right', 'config' => ['url' => ['source' => FieldConfig::SOURCE_STATIC, 'value' => 'http://shopware.com/image.jpg']]],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->cmsPageRepository->create([$page], $this->salesChannelContext->getContext());

        return $page['id'];
    }
}
