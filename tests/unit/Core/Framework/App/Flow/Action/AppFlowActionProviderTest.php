<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Content\Flow\Dispatching\Storer\OrderStorer;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\App\Flow\Action\AppFlowActionProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(AppFlowActionProvider::class)]
class AppFlowActionProviderTest extends TestCase
{
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

        $ids = new IdsCollection();
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

        $awareEvent = new CheckoutOrderPlacedEvent(Context::createDefaultContext(), $order, 'testSalesChannelId');

        $orderStorer = new OrderStorer($orderRepo, $this->createMock(EventDispatcherInterface::class));

        $flow = (new FlowFactory([$orderStorer]))->create($awareEvent);
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
