<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\App\Exception\InvalidAppFlowActionVariableException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;

class AppFlowActionProvider
{
    private Connection $connection;

    private BusinessEventEncoder $businessEventEncoder;

    private StringTemplateRenderer $templateRenderer;

    /**
     * @internal
     */
    public function __construct(
        Connection $connection,
        BusinessEventEncoder $businessEventEncoder,
        StringTemplateRenderer $templateRenderer
    ) {
        $this->connection = $connection;
        $this->businessEventEncoder = $businessEventEncoder;
        $this->templateRenderer = $templateRenderer;
    }

    public function getWebhookData(FlowEvent $event, string $appFlowActionId): array
    {
        $context = $event->getContext();

        $appFlowActionData = $this->getAppFlowActionData($appFlowActionId);

        if (empty($appFlowActionData)) {
            return [];
        }

        $availableData = $this->businessEventEncoder->encode($event->getEvent());
        $data = array_merge(
            $event->getConfig(),
            $availableData
        );

        $configData = $this->resolveParamsData($event->getConfig(), $data, $context, $appFlowActionId);
        $data = array_merge(
            $configData,
            $availableData
        );

        $parameters = array_column(json_decode($appFlowActionData['parameters'], true), 'value', 'name');
        $headers = array_column(json_decode($appFlowActionData['headers'], true), 'value', 'name');

        return [
            'payload' => $this->resolveParamsData($parameters, $data, $context, $appFlowActionId),
            'headers' => $this->resolveParamsData($headers, $data, $context, $appFlowActionId),
        ];
    }

    /**
     * @throws InvalidAppFlowActionVariableException
     */
    private function resolveParamsData(array $params, array $data, Context $context, string $appFlowActionId): array
    {
        $paramData = [];

        foreach ($params as $key => $param) {
            try {
                $paramData[$key] = $this->templateRenderer->render($param, $data, $context);
            } catch (\Throwable $e) {
                throw new InvalidAppFlowActionVariableException($appFlowActionId, $param, $e->getMessage(), $e->getCode());
            }
        }

        return $paramData;
    }

    private function getAppFlowActionData(string $appFlowActionId): array
    {
        return $this->connection->fetchAssociative(
            'SELECT `parameters`, `headers` FROM `app_flow_action` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($appFlowActionId)]
        ) ?: [];
    }
}
