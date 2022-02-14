<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\App\Event\AppFlowActionEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;

class AppFlowActionProvider
{
    private EntityRepositoryInterface $appFlowActionRepository;

    private BusinessEventEncoder $businessEventEncoder;

    private StringTemplateRenderer $templateRenderer;

    private LoggerInterface $logger;

    public function __construct(
        EntityRepositoryInterface $appFlowActionRepository,
        BusinessEventEncoder $businessEventEncoder,
        StringTemplateRenderer $templateRenderer,
        LoggerInterface $logger
    ) {
        $this->appFlowActionRepository = $appFlowActionRepository;
        $this->businessEventEncoder = $businessEventEncoder;
        $this->templateRenderer = $templateRenderer;
        $this->logger = $logger;
    }

    public function getWebhookData(AppFlowActionEvent $event): array
    {
        $flowEvent = $event->getEvent();
        $context = $flowEvent->getContext();
        $actionName = $flowEvent->getActionName();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $actionName));

        $appFlowAction = $this->appFlowActionRepository->search($criteria, $context)->first();

        if (!$appFlowAction) {
            return [];
        }

        $data = array_merge(
            $flowEvent->getConfig(),
            $this->businessEventEncoder->encode($flowEvent->getEvent())
        );

        return [
            'payload' => $this->resolveParamsData($appFlowAction->getParameters(), $data, $context),
            'headers' => $this->resolveParamsData($appFlowAction->getHeaders(), $data, $context),
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
}
