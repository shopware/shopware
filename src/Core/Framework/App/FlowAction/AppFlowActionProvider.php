<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\App\Event\AppFlowActionEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;

class AppFlowActionProvider
{
    private Connection $connection;

    private BusinessEventEncoder $businessEventEncoder;

    private StringTemplateRenderer $templateRenderer;

    private LoggerInterface $logger;

    public function __construct(
        Connection $connection,
        BusinessEventEncoder $businessEventEncoder,
        StringTemplateRenderer $templateRenderer,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->businessEventEncoder = $businessEventEncoder;
        $this->templateRenderer = $templateRenderer;
        $this->logger = $logger;
    }

    public function getWebhookData(AppFlowActionEvent $event): array
    {
        $flowEvent = $event->getEvent();
        $context = $flowEvent->getContext();

        $appFlowActionData = $this->getAppFlowActionData($event->getAppFlowActionId());

        if (empty($appFlowActionData)) {
            return [];
        }

        $data = array_merge(
            $flowEvent->getConfig(),
            $this->businessEventEncoder->encode($flowEvent->getEvent())
        );

        return [
            'payload' => $this->resolveParamsData(json_decode($appFlowActionData['parameters'], true), $data, $context),
            'headers' => $this->resolveParamsData(json_decode($appFlowActionData['headers'], true), $data, $context),
        ];
    }

    private function resolveParamsData(array $params, array $data, Context $context): array
    {
        $paramData = [];

        foreach ($params as $param) {
            try {
                $paramData[$param['name']] = $this->templateRenderer->render($param['value'], $data, $context);
            } catch (\Throwable $e) {
                $this->logger->error(
                    "Could not render template with error message:\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code:' . $e->getCode() . "\n"
                    . 'Template source:'
                    . $param['value'] . "\n"
                    . "Template data: \n"
                    . \json_encode($data) . "\n"
                );

                $paramData[$param['name']] = null;
            }
        }

        return $paramData;
    }

    private function getAppFlowActionData(string $appFlowActionId): array
    {
        $data = $this->connection->fetchAssociative(
            'SELECT `parameters`, `headers` FROM `app_flow_action` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($appFlowActionId)]
        );

        return $data ? $data : [];
    }
}
