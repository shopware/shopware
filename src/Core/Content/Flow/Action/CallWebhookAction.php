<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Action;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\Exception\WebhookActionConfigurationException;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\WebhookAware;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class CallWebhookAction extends FlowAction
{
    private const TIMEOUT = 20;
    private const CONNECT_TIMEOUT = 10;

    private ClientInterface $guzzleClient;

    private LoggerInterface $logger;

    private StringTemplateRenderer $templateRenderer;

    public function __construct(Client $guzzleClient, StringTemplateRenderer $templateRenderer, LoggerInterface $logger)
    {
        $this->guzzleClient = $guzzleClient;
        $this->templateRenderer = $templateRenderer;
        $this->logger = $logger;
    }

    public function getName(): string
    {
        return FlowAction::CALL_WEBHOOK;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FlowAction::CALL_WEBHOOK => 'handle',
        ];
    }

    public function requirements(): array
    {
        return [WebhookAware::class];
    }

    public function handle(FlowEvent $event): void
    {
        $config = $event->getConfig();

        $options = $config['options'];
        $options['connect_timeout'] = self::CONNECT_TIMEOUT;
        $options['timeout'] = self::TIMEOUT;

        if (\array_key_exists(RequestOptions::AUTH, $options) && !$config['authActive']) {
            unset($options[RequestOptions::AUTH]);
        }

        $event = $event->getFlowState()->event;
        $data = $this->getAvailableData($event);
        $options = $this->buildRequestOptions($options, $data, $event->getContext());

        try {
            $this->guzzleClient->request($config['method'], $config['baseUrl'], $options);
        } catch (GuzzleException $e) {
            $this->logger->notice(sprintf('Webhook execution failed to target url "%s".', $config['baseUrl']), [
                'exceptionMessage' => $e->getMessage(),
                'statusCode' => $e->getCode(),
            ]);
        }
    }

    private function buildRequestOptions(array $options, array $data, Context $context): array
    {
        if (\array_key_exists(RequestOptions::HEADERS, $options)) {
            $options[RequestOptions::HEADERS] = $this->resolveOptionParams($options[RequestOptions::HEADERS], $data, $context);
        }

        if (\array_key_exists(RequestOptions::QUERY, $options)) {
            $options[RequestOptions::QUERY] = $this->resolveOptionParams($options[RequestOptions::QUERY], $data, $context);
        }

        if (\array_key_exists(RequestOptions::FORM_PARAMS, $options)) {
            $options[RequestOptions::FORM_PARAMS] = $this->resolveOptionParams($options[RequestOptions::FORM_PARAMS], $data, $context);
        }

        if (\array_key_exists(RequestOptions::BODY, $options)) {
            $options[RequestOptions::BODY] = $this->resolveParamsData($options[RequestOptions::BODY], $data, $context);
        }

        return $options;
    }

    private function resolveOptionParams(array $params, array $data, Context $context): array
    {
        foreach ($params as $key => $value) {
            if (!$this->isContainParameter($value)) {
                continue;
            }

            $params[$key] = $this->resolveParamsData($value, $data, $context);
        }

        return $params;
    }

    private function resolveParamsData(string $template, array $data, Context $context): ?string
    {
        try {
            return $this->templateRenderer->render($template, $data, $context);
        } catch (\Throwable $e) {
            $this->logger->error(
                "Could not render template with error message:\n"
                . $e->getMessage() . "\n"
                . 'Error Code:' . $e->getCode() . "\n"
                . 'Template source:'
                . $template . "\n"
                . "Template data: \n"
                . json_encode($data) . "\n"
            );

            return null;
        }
    }

    private function getAvailableData(WebhookAware $event): array
    {
        $data = [];

        foreach (array_keys($event::getAvailableData()->toArray()) as $key) {
            $getter = 'get' . ucfirst($key);
            if (!method_exists($event, $getter)) {
                throw new WebhookActionConfigurationException('Data for ' . $key . ' not available.', \get_class($event));
            }
            $data[$key] = $event->$getter();
        }

        return $data;
    }

    private function isContainParameter(string $string): bool
    {
        if (str_contains($string, '{{') && str_contains($string, '}}')) {
            return true;
        }

        return false;
    }
}
