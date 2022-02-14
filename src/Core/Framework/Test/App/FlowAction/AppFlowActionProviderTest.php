<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\FlowAction;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionCollection;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppFlowActionEvent;
use Shopware\Core\Framework\App\FlowAction\AppFlowActionProvider;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Test\Event\TestBusinessEvent;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AppFlowActionProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetWebhookData(): void
    {
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
        $app = $this->createMock(AppEntity::class);
        $context = $this->createMock(SalesChannelContext::class);

        $appFlowAction = new AppFlowActionEntity();
        $appFlowAction->setUniqueIdentifier('11111');
        $appFlowAction->setName($actionName);
        $appFlowAction->setHeaders($headers);
        $appFlowAction->setParameters($params);
        $appFlowAction->setApp($app);

        $emptySearchResult = new EntitySearchResult(
            'app_flow_action',
            1,
            new AppFlowActionCollection([$appFlowAction]),
            null,
            new Criteria(),
            $context->getContext()
        );
        $this->createMock(EntityRepositoryInterface::class);

        $appFlowActionRepository = $this->createMock(EntityRepositoryInterface::class);
        $appFlowActionRepository->method('search')->willReturn($emptySearchResult);

        $flowState = new FlowState(new TestBusinessEvent($context->getContext()));

        $flowEvent = new FlowEvent(
            $actionName,
            $flowState,
            $config
        );

        $appFlowActionEvent = new AppFlowActionEvent($flowEvent);

        $appFlowActionProvider = new AppFlowActionProvider(
            $appFlowActionRepository,
            $this->getContainer()->get(BusinessEventEncoder::class),
            $this->getContainer()->get(StringTemplateRenderer::class),
            $this->createMock(Logger::class)
        );

        $webhookData = $appFlowActionProvider->getWebhookData($appFlowActionEvent);

        static::assertEquals(['param1' => 'Text 1', 'param2' => 'Text 2 and Text 3'], $webhookData['payload']);
        static::assertEquals(['content-type' => 'application/json'], $webhookData['headers']);
    }
}
