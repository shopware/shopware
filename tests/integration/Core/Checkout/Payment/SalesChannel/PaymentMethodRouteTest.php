<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Payment\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Hook\PaymentMethodRouteHook;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Integration\PaymentHandler\AsyncTestPaymentHandler;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Group('store-api')]
class PaymentMethodRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'paymentMethodId' => $this->ids->get('payment'),
            'paymentMethods' => [
                ['id' => $this->ids->get('payment')],
                ['id' => $this->ids->get('payment2')],
                ['id' => $this->ids->get('payment3')],
            ],
        ]);

        $this->salesChannelContext = $this->getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    public function testLoading(): void
    {
        $this->browser->request('POST', '/store-api/payment-method');

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $ids = array_column($response['elements'], 'id');

        static::assertSame(3, $response['total']);
        static::assertContains($this->ids->get('payment'), $ids);
        static::assertContains($this->ids->get('payment2'), $ids);
        static::assertContains($this->ids->get('payment3'), $ids);

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(PaymentMethodRouteHook::HOOK_NAME, $traces);
    }

    public function testSorting(): void
    {
        $paymentMethodRoute = $this->getContainer()->get(PaymentMethodRoute::class);

        $request = new Request();

        $unselectedPaymentResult = $paymentMethodRoute->load($request, $this->salesChannelContext, new Criteria());
        static::assertNotNull($unselectedPaymentResult->getPaymentMethods()->last());
        $lastPaymentMethodId = $unselectedPaymentResult->getPaymentMethods()->last()->getId();

        $this->salesChannelContext->getPaymentMethod()->setId($lastPaymentMethodId);
        $selectedPaymentMethodResult = $paymentMethodRoute->load($request, $this->salesChannelContext, new Criteria());

        static::assertNotNull($selectedPaymentMethodResult->getPaymentMethods()->first());
        static::assertSame($lastPaymentMethodId, $selectedPaymentMethodResult->getPaymentMethods()->first()->getId());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(PaymentMethodRouteHook::HOOK_NAME, $traces);
    }

    public function testIncludes(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/payment-method',
            [
                'includes' => [
                    'payment_method' => [
                        'name',
                    ],
                ],
            ]
        );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(3, $response['total']);
        static::assertArrayHasKey('name', $response['elements'][0]);
        static::assertArrayNotHasKey('id', $response['elements'][0]);
    }

    public function testFilteredOutGet(): void
    {
        $this->browser
            ->request(
                'GET',
                '/store-api/payment-method?onlyAvailable=1',
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(2, $response['total']);
        static::assertCount(2, $response['elements']);
        static::assertNotContains($this->ids->get('payment3'), array_column($response['elements'], 'id'));

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(PaymentMethodRouteHook::HOOK_NAME, $traces);
    }

    public function testFilteredOutPost(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/payment-method',
                ['onlyAvailable' => 1],
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(2, $response['total']);
        static::assertCount(2, $response['elements']);
        static::assertNotContains($this->ids->get('payment3'), array_column($response['elements'], 'id'));

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(PaymentMethodRouteHook::HOOK_NAME, $traces);
    }

    private function createData(): void
    {
        $data = [
            [
                'id' => $this->ids->create('payment'),
                'name' => 'Payment 1',
                'technicalName' => 'payment_test',
                'active' => true,
                'handlerIdentifier' => AsyncTestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                    'conditions' => [
                        [
                            'type' => 'dateRange',
                            'value' => [
                                'fromDate' => '2000-06-07T11:37:51+02:00',
                                'toDate' => '2099-06-07T11:37:51+02:00',
                                'useTime' => false,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => $this->ids->create('payment2'),
                'name' => 'Payment 2',
                'technicalName' => 'payment_test2',
                'active' => true,
                'handlerIdentifier' => AsyncTestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                    'conditions' => [
                        [
                            'type' => 'dateRange',
                            'value' => [
                                'fromDate' => '2000-06-07T11:37:51+02:00',
                                'toDate' => '2099-06-07T11:37:51+02:00',
                                'useTime' => false,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => $this->ids->create('payment3'),
                'name' => 'Payment 3',
                'technicalName' => 'payment_test3',
                'active' => true,
                'handlerIdentifier' => AsyncTestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                    'conditions' => [
                        [
                            'type' => 'dateRange',
                            'value' => [
                                'fromDate' => '2000-06-07T11:37:51+02:00',
                                'toDate' => '2000-06-07T11:37:51+02:00',
                                'useTime' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->getContainer()->get('payment_method.repository')
            ->create($data, Context::createDefaultContext());
    }
}
