<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Export\Summarizer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Export\CollectorDataConverter;
use Shopware\Core\Profiling\Export\ExportLimits;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;

/**
 * @internal
 */
#[Package('framework')]
class RequestSummarizer implements PanelSummarizer
{
    public function __construct(private readonly CollectorDataConverter $converter)
    {
    }

    public function supports(DataCollectorInterface $collector): bool
    {
        return $collector instanceof RequestDataCollector;
    }

    public function summarize(DataCollectorInterface $collector, mixed $data): array
    {
        \assert($collector instanceof RequestDataCollector);

        $limits = new ExportLimits(depth: 4, items: 50, string: 0);
        $controller = $this->converter->convert($collector->getController(), $limits);

        return [
            'method' => $collector->getMethod(),
            'path' => $collector->getPathInfo(),
            'route' => $collector->getRoute(),
            'routeParams' => $this->converter->convert($collector->getRouteParams(), $limits),
            'controller' => \is_array($controller) && isset($controller['class'], $controller['method']) ? $controller['class'] . '::' . $controller['method'] : $controller,
            'status' => $collector->getStatusCode(),
            'contentType' => $collector->getContentType(),
            'query' => $this->converter->convert($collector->getRequestQuery()->all(), $limits),
            'body' => $this->converter->convert($collector->getRequestRequest()->all(), $limits),
            'requestHeaders' => $this->converter->convert($collector->getRequestHeaders()->all(), $limits),
            'responseHeaders' => $this->converter->convert($collector->getResponseHeaders()->all(), $limits),
            'cookies' => $this->converter->convert($collector->getRequestCookies()->all(), $limits),
            'responseCookies' => $this->converter->convert($collector->getResponseCookies()->all(), $limits),
            'sessionAttributes' => $this->converter->convert($collector->getSessionAttributes(), $limits),
            'serverVars' => $collector->getRequestServer()->count(),
        ];
    }
}
