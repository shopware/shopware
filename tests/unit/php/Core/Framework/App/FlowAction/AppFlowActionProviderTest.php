<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\FlowAction;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Content\Flow\Dispatching\Storer\OrderStorer;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\App\FlowAction\AppFlowActionProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\Event\TestBusinessEvent;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\FlowAction\AppFlowActionProvider
 */
class AppFlowActionProviderTest extends TestCase
{
    public function testGetWebhookData(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $actionName = 'app.send_telegram_message';
        $params = [
            ['name' => 'param1', 'type' => 'string', 'value' => '{{ config1 }}'],
            ['name' => 'param2', 'type' => 'string', 'value' => '{{ config2 }} and {{ config3 }}'],
        ];
        $headers = [
            ['name' => 'content-type', 'type' => 'string', 'value' => 'application/json'],
        ];
        $config = [
            'config1' => 'Text 1',
            'config2' => 'Text 2',
            'config3' => 'Text 3',
        ];

        $context = $this->createMock(SalesChannelContext::class);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('fetchAssociative')
            ->willReturn(
                ['parameters' => json_encode($params), 'headers' => json_encode($headers)]
            );

        $flowState = new FlowState(new TestBusinessEvent($context->getContext()));

        $flowEvent = new FlowEvent(
            $actionName,
            $flowState,
            $config
        );

        $stringTemplateRender = $this->createMock(StringTemplateRenderer::class);
        $stringTemplateRender->expects(static::exactly(6))
            ->method('render')
            ->willReturnOnConsecutiveCalls(
                'Text 1',
                'Text 2',
                'Text 3',
                'Text 1',
                'Text 2 and Text 3',
                'application/json'
            );

        $appFlowActionProvider = new AppFlowActionProvider(
            $connection,
            $this->createMock(BusinessEventEncoder::class),
            $stringTemplateRender
        );

        $webhookData = $appFlowActionProvider->getWebhookData($flowEvent, '1111');

        static::assertEquals(['param1' => 'Text 1', 'param2' => 'Text 2 and Text 3'], $webhookData['payload']);
        static::assertEquals(['content-type' => 'application/json'], $webhookData['headers']);
    }

    public function testGetWebhookPayloadAndHeaders(): void
    {
        $params = [
            ['name' => 'param1', 'type' => 'string', 'value' => '{{ config1 }}'],
            ['name' => 'param2', 'type' => 'string', 'value' => '{{ config2 }} and {{ config3 }}'],
        ];

        $headers = [
            ['name' => 'content-type', 'type' => 'string', 'value' => 'application/json'],
        ];

        $config = [
            'config1' => 'Text 1',
            'config2' => 'Text 2',
            'config3' => 'Text 3',
        ];

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('fetchAssociative')
            ->willReturn(
                ['parameters' => json_encode($params), 'headers' => json_encode($headers)]
            );

        $ids = new TestDataCollection();
        $order = new OrderEntity();
        $order->setId($ids->get('orderId'));

        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->expects(static::once())
            ->method('get')
            ->willReturn($order);

        $orderRepo = $this->createMock(EntityRepository::class);
        $orderRepo->expects(static::once())
            ->method('search')
            ->willReturn($entitySearchResult);

        $awareEvent = new CheckoutOrderPlacedEvent(Context::createDefaultContext(), $order, 'asdsad');

        $orderStorer = new OrderStorer($orderRepo);
        $flowFactory = new FlowFactory([$orderStorer]);

        $flow = $flowFactory->create($awareEvent);
        $flow->setConfig($config);

        $stringTemplateRender = $this->createMock(StringTemplateRenderer::class);
        $stringTemplateRender->expects(static::exactly(6))
            ->method('render')
            ->willReturnOnConsecutiveCalls(
                'Text 1',
                'Text 2',
                'Text 3',
                'Text 1',
                'Text 2 and Text 3',
                'application/json'
            );

        $appFlowActionProvider = new AppFlowActionProvider(
            $connection,
            $this->createMock(BusinessEventEncoder::class),
            $stringTemplateRender
        );

        $webhookData = $appFlowActionProvider->getWebhookPayloadAndHeaders($flow, $ids->get('appFlowActionId'));

        static::assertEquals(['param1' => 'Text 1', 'param2' => 'Text 2 and Text 3'], $webhookData['payload']);
        static::assertEquals(['content-type' => 'application/json'], $webhookData['headers']);
    }
}
