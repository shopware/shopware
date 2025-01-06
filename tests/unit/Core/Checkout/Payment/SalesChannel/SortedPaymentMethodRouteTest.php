<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Hook\PaymentMethodRouteHook;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Checkout\Payment\SalesChannel\SortedPaymentMethodRoute;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(SortedPaymentMethodRoute::class)]
class SortedPaymentMethodRouteTest extends TestCase
{
    private MockObject&AbstractPaymentMethodRoute $decorated;

    private MockObject&ScriptExecutor $executor;

    private SortedPaymentMethodRoute $sortedRoute;

    private SalesChannelContext $context;

    private PaymentMethodRouteResponse $response;

    protected function setUp(): void
    {
        $this->decorated = $this->createMock(AbstractPaymentMethodRoute::class);
        $this->executor = $this->createMock(ScriptExecutor::class);
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setPaymentMethodId(Uuid::randomHex());
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId($salesChannel->getPaymentMethodId());
        $this->context = Generator::createSalesChannelContext(
            baseContext: new Context(new SalesChannelApiSource(Uuid::randomHex())),
            salesChannel: $salesChannel,
            paymentMethod: $paymentMethod,
        );
        $this->response = new PaymentMethodRouteResponse(
            new EntitySearchResult(
                'entity',
                1,
                new PaymentMethodCollection([$paymentMethod]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );
        $this->sortedRoute = new SortedPaymentMethodRoute($this->decorated, $this->executor);
    }

    public function testTriggersScriptHookExecution(): void
    {
        $this->decorated
            ->expects(static::once())
            ->method('load')
            ->willReturn($this->response);

        $this->executor->method('execute')->with(static::callback(fn (PaymentMethodRouteHook $hook) => $hook->getCollection() === $this->response->getPaymentMethods()
            && $hook->getSalesChannelContext() === $this->context
            && $hook->isOnlyAvailable()));

        $response = $this->sortedRoute->load(new Request(['onlyAvailable' => true]), $this->context, new Criteria());
        static::assertCount(1, $response->getPaymentMethods());
    }
}
