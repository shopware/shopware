<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Shopware\Core\Framework\Api\Cors\CorsHeaderProviderInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
class CorsListener implements EventSubscriberInterface
{
    /**
     * The MCP headers must be part of the global lists because this listener
     * answers OPTIONS preflight requests before routing, so the target route
     * (e.g. the MCP endpoints) is unknown at this point.
     */
    private const DEFAULT_HEADERS = [
        'Content-Type',
        'Authorization',
        PlatformRequest::HEADER_CONTEXT_TOKEN,
        PlatformRequest::HEADER_ACCESS_KEY,
        PlatformRequest::HEADER_LANGUAGE_ID,
        PlatformRequest::HEADER_VERSION_ID,
        PlatformRequest::HEADER_INHERITANCE,
        PlatformRequest::HEADER_INDEXING_BEHAVIOR,
        PlatformRequest::HEADER_INCLUDE_SEO_URLS,
        PlatformRequest::HEADER_MCP_SESSION_ID,
        PlatformRequest::HEADER_MCP_PROTOCOL_VERSION,
    ];

    /**
     * The providers are fixed at container compile time, so the header values are built once
     * and reused for every response.
     *
     * @var array{allow: string, expose: string}|null
     */
    private ?array $headerValues = null;

    /**
     * @param iterable<CorsHeaderProviderInterface> $headerProviders
     */
    public function __construct(private readonly iterable $headerProviders = [])
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9999],
            KernelEvents::RESPONSE => ['onKernelResponse', 9999],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $method = $event->getRequest()->getRealMethod();

        if ($method === 'OPTIONS') {
            $response = new Response();
            $event->setResponse($response);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headerValues = $this->getHeaderValues();

        $response = $event->getResponse();
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE');
        $response->headers->set('Access-Control-Allow-Headers', $headerValues['allow']);
        $response->headers->set('Access-Control-Expose-Headers', $headerValues['expose']);
    }

    /**
     * @return array{allow: string, expose: string}
     */
    private function getHeaderValues(): array
    {
        if ($this->headerValues !== null) {
            return $this->headerValues;
        }

        $allowed = self::DEFAULT_HEADERS;
        $exposed = self::DEFAULT_HEADERS;

        foreach ($this->headerProviders as $provider) {
            $allowed = [...$allowed, ...$provider->getAllowedHeaders()];
            $exposed = [...$exposed, ...$provider->getExposedHeaders()];
        }

        $this->headerValues = [
            'allow' => self::toHeaderValue($allowed),
            'expose' => self::toHeaderValue($exposed),
        ];

        return $this->headerValues;
    }

    /**
     * @param list<string> $headers
     */
    private static function toHeaderValue(array $headers): string
    {
        $unique = [];
        foreach ($headers as $header) {
            // header names are case-insensitive, so keep the first spelling that was contributed
            $unique[mb_strtolower($header)] ??= $header;
        }

        return implode(',', $unique);
    }
}
