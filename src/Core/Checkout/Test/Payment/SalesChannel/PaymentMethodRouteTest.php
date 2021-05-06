<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\AsyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class PaymentMethodRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'paymentMethodId' => $this->ids->get('payment'),
            'paymentMethods' => [
                ['id' => $this->ids->get('payment')],
                ['id' => $this->ids->get('payment2')],
            ],
        ]);

        $this->salesChannelContext = $this->getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
    }

    public function testLoading(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/payment-method',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $ids = array_column($response['elements'], 'id');

        static::assertSame(2, $response['total']);
        static::assertContains($this->ids->get('payment'), $ids);
        static::assertContains($this->ids->get('payment2'), $ids);
    }

    public function testSorting(): void
    {
        $paymentMethodRoute = $this->getContainer()->get(PaymentMethodRoute::class);

        $request = new Request();

        $unselectedPaymentResult = $paymentMethodRoute->load($request, $this->salesChannelContext, new Criteria());
        $lastPaymentMethodId = $unselectedPaymentResult->getPaymentMethods()->last()->getId();

        $this->salesChannelContext->getPaymentMethod()->setId($lastPaymentMethodId);
        $selectedPaymentMethodResult = $paymentMethodRoute->load($request, $this->salesChannelContext, new Criteria());

        static::assertSame($lastPaymentMethodId, $selectedPaymentMethodResult->getPaymentMethods()->first()->getId());
    }

    public function testIncludes(): void
    {
        $this->browser
            ->request(
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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(2, $response['total']);
        static::assertArrayHasKey('name', $response['elements'][0]);
        static::assertArrayNotHasKey('id', $response['elements'][0]);
    }

    private function createData(): void
    {
        $data = [
            [
                'id' => $this->ids->create('payment'),
                'name' => $this->ids->get('payment'),
                'active' => true,
                'handlerIdentifier' => AsyncTestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                ],
            ],
            [
                'id' => $this->ids->create('payment2'),
                'name' => $this->ids->get('payment2'),
                'active' => true,
                'handlerIdentifier' => AsyncTestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                ],
            ],
        ];

        $this->getContainer()->get('payment_method.repository')
            ->create($data, $this->ids->context);
    }
}
