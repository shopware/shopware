<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\App\Exception\InvalidAppFlowActionVariableException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;

#[Package('core')]
class AppFlowActionProvider
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly BusinessEventEncoder $businessEventEncoder,
        private readonly StringTemplateRenderer $templateRenderer
    ) {
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getWebhookPayloadAndHeaders(StorableFlow $flow, string $appFlowActionId): array
    {
        $context = $flow->getContext();

        $appFlowActionData = $this->getAppFlowActionData($appFlowActionId);

        if (empty($appFlowActionData)) {
            return [];
        }

        $additionData = $this->businessEventEncoder->encodeData($flow->data(), $flow->stored());
        $data = [...$flow->getConfig(), ...$additionData];

        $configData = $this->resolveParamsData($flow->getConfig(), $data, $context, $appFlowActionId);
        /** @var array<string, mixed> $data */
        $data = [...$configData, ...$additionData];

        /** @var string $parameterData */
        $parameterData = $appFlowActionData['parameters'];
        /** @var array<string, string> $parameters */
        $parameters = array_column(json_decode($parameterData, true, 512, \JSON_THROW_ON_ERROR), 'value', 'name');

        /** @var string $headersData */
        $headersData = $appFlowActionData['headers'];
        /** @var array<string, string> $headers */
        $headers = array_column(json_decode($headersData, true, 512, \JSON_THROW_ON_ERROR), 'value', 'name');

        return [
            'payload' => $this->resolveParamsData($parameters, $data, $context, $appFlowActionId),
            'headers' => $this->resolveParamsData($headers, $data, $context, $appFlowActionId),
        ];
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $data
     *
     * @throws InvalidAppFlowActionVariableException
     *
     * @return array<string, string>
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
