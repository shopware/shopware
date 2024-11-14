<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Review;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewSaveRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('store-api')]
class ProductReviewSaveRouteTest extends TestCase
{
    use EventDispatcherBehaviour;
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->setVisibilities();
    }

    public function testRequiresLogin(): void
    {
        $this->browser->request('POST', $this->getUrl());

        $response = $this->browser->getResponse();

        static::assertEquals(403, $response->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals($response['errors'][0]['code'], 'CHECKOUT__CUSTOMER_NOT_LOGGED_IN');
    }

    #[DataProvider('provideContentData')]
    public function testCreate(string $content, string $expectedContent): void
    {
        $this->login($this->browser);

        $this->assertReviewCount(0);

        $this->browser->request('POST', $this->getUrl(), [
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => $content,
        ]);

        $response = $this->browser->getResponse();

        static::assertEquals(204, $response->getStatusCode(), print_r($this->browser->getResponse()->getContent(), true));

        $this->assertReviewCount(1);

        $this->assertReviewContent($expectedContent);
    }

    public function testUpdate(): void
    {
        $this->login($this->browser);

        $this->assertReviewCount(0);

        $id = Uuid::randomHex();

        $this->browser->request('POST', $this->getUrl(), [
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
        ]);

        $response = $this->browser->getResponse();

        static::assertEquals(204, $response->getStatusCode(), print_r($this->browser->getResponse()->getContent(), true));

        $this->assertReviewCount(1);

        $this->browser->request('POST', $this->getUrl(), [
            'id' => $id,
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
        ]);
        $this->assertReviewCount(1);
    }

    public function testValidation(): void
    {
        $this->login($this->browser);

        $this->browser->request('POST', $this->getUrl());

        $response = $this->browser->getResponse();

        static::assertEquals(400, $response->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals($response['errors'][0]['source']['pointer'], '/title');
        static::assertEquals($response['errors'][1]['source']['pointer'], '/content');
    }

    public function testCustomerValidation(): void
    {
        $this->login($this->browser);

        $this->assertReviewCount(0);

        $id = Uuid::randomHex();

        // Create review
        $this->browser->request('POST', $this->getUrl(), [
            'id' => $id,
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
        ]);

        // Re-login as another user
        $this->login($this->browser);

        // Try to use the id from previous review which is not attached to this customer
        $this->browser->request('POST', $this->getUrl(), [
            'id' => $id,
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
        ]);

        $response = $this->browser->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('VIOLATION::ENTITY_DOES_NOT_EXISTS', $content['errors'][0]['code']);
    }

    public function testMailIsSent(): void
    {
        $customerId = $this->createCustomer();
        $salesChannelContext = $this->createSalesChannelContext([], [
            SalesChannelContextService::CUSTOMER_ID => $customerId,
        ]);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $caughtEvent = null;
        $this->addEventListener(
            $dispatcher,
            MailBeforeSentEvent::class,
            static function (MailBeforeSentEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $data = new RequestDataBag([
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
            'points' => 3,
        ]);
        $this->getContainer()->get(ProductReviewSaveRoute::class)->save(
            $this->ids->get('product'),
            $data,
            $salesChannelContext
        );

        $this->resetEventDispatcher();

        static::assertInstanceOf(MailBeforeSentEvent::class, $caughtEvent);
        $bodyText = $caughtEvent->getMessage()->getTextBody();
        $bodyText = \is_string($bodyText) ? $bodyText : '';
        static::assertStringContainsString($data->get('title'), $bodyText);
        static::assertStringContainsString($data->get('content'), $bodyText);
        static::assertStringContainsString($this->ids->get('unique-name'), $bodyText);
    }

    public static function provideContentData(): \Generator
    {
        yield 'simple' => [
            'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
            'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
        ];
        yield 'html' => [
            '<a href="https://localhost">Lorem ipsum dolor sit amet, consetetur sadipscing elitr</a>',
            'Lorem ipsum dolor sit amet, consetetur sadipscing elitr',
        ];
        yield 'script' => [
            '<script>alert("Lorem ipsum dolor sit amet, consetetur sadipscing elitr")</script>',
            'alert("Lorem ipsum dolor sit amet, consetetur sadipscing elitr")',
        ];
        yield 'javascript' => [
            '<script>alert("foo")</script><p>foo</p><script>alert("foo")</script>',
            'alert("foo")fooalert("foo")',
        ];
        yield 'javascript with attributes' => [
            '<script type="text/javascript">alert("foo")</script><p>foo</p><script>alert("foo")</script>',
            'alert("foo")fooalert("foo")',
        ];
        yield 'javascript with attributes and spaces' => [
            '<script type = "text/javascript">alert("foo")</script><p>foo</p><script>alert("foo")</script>',
            'alert("foo")fooalert("foo")',
        ];
    }

    private function assertReviewCount(int $expected): void
    {
        $count = $this->getContainer()
            ->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM product_review WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($this->ids->get('product'))]);

        static::assertEquals($expected, $count);
    }

    private function createData(): void
    {
        $product = [
            'id' => $this->ids->create('product'),
            'manufacturer' => ['id' => $this->ids->create('manufacturer-'), 'name' => 'test-'],
            'productNumber' => $this->ids->get('product'),
            'name' => $this->ids->get('unique-name'),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'active' => true,
        ];

        $this->getContainer()->get('product.repository')
            ->create([$product], Context::createDefaultContext());
    }

    private function setVisibilities(): void
    {
        $update = [
            [
                'id' => $this->ids->get('product'),
                'visibilities' => [
                    ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ];
        $this->getContainer()->get('product.repository')
            ->update($update, Context::createDefaultContext());
    }

    private function getUrl(): string
    {
        return '/store-api/product/' . $this->ids->get('product') . '/review';
    }

    private function assertReviewContent(string $expectedContent): void
    {
        $content = $this->getContainer()
            ->get(Connection::class)
            ->fetchOne('SELECT content FROM product_review WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($this->ids->get('product'))]);

        static::assertEquals($expectedContent, $content);
    }
}
