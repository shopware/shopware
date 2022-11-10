<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\App\Exception\InvalidAppFlowActionVariableException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;

/**
 * @package core
 */
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

    /**
     * @return array<string, array<int|string, string>>
     *
     * @deprecated tag:v6.5.0 Will be removed, use AppFlowActionProvider::getWebhookPayloadAndHeaders instead
     */
    public function getWebhookData(FlowEvent $event, string $appFlowActionId): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

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

        /** @var string $parameterData */
        $parameterData = $appFlowActionData['parameters'];
        $parameters = array_column(json_decode($parameterData, true), 'value', 'name');

        /** @var string $headersData */
        $headersData = $appFlowActionData['headers'];
        $headers = array_column(json_decode($headersData, true), 'value', 'name');

        return [
            'payload' => $this->resolveParamsData($parameters, $data, $context, $appFlowActionId),
            'headers' => $this->resolveParamsData($headers, $data, $context, $appFlowActionId),
        ];
    }

    /**
     * @return array<string, array<int|string, string>>
     */
    public function getWebhookPayloadAndHeaders(StorableFlow $flow, string $appFlowActionId): array
    {
        $context = $flow->getContext();

        $appFlowActionData = $this->getAppFlowActionData($appFlowActionId);

        if (empty($appFlowActionData)) {
            return [];
        }

        $additionData = $this->businessEventEncoder->encodeData($flow->data(), $flow->stored());
        $data = array_merge(
            $flow->getConfig(),
            $additionData
        );

        $configData = $this->resolveParamsData($flow->getConfig(), $data, $context, $appFlowActionId);
        $data = array_merge(
            $configData,
            $additionData
        );

        /** @var string $parameterData */
        $parameterData = $appFlowActionData['parameters'];
        $parameters = array_column(json_decode($parameterData, true), 'value', 'name');

        /** @var string $headersData */
        $headersData = $appFlowActionData['headers'];
        $headers = array_column(json_decode($headersData, true), 'value', 'name');

        return [
            'payload' => $this->resolveParamsData($parameters, $data, $context, $appFlowActionId),
            'headers' => $this->resolveParamsData($headers, $data, $context, $appFlowActionId),
        ];
    }

    /**
     * @param array<int|string, mixed> $params
     * @param array<int|string, mixed> $data
     *
     * @throws InvalidAppFlowActionVariableException
     *
     * @return array<int|string, string>
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

    /**
     * @throws Exception
     *
     * @return array<string, string|null>
     */
    private function getAppFlowActionData(string $appFlowActionId): array
    {
        return $this->connection->fetchAssociative(
            'SELECT `parameters`, `headers` FROM `app_flow_action` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($appFlowActionId)]
        ) ?: [];
    }
}
