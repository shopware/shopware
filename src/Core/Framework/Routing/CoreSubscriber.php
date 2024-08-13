<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('core')]
readonly class CoreSubscriber implements EventSubscriberInterface
{
    /**
     * @param array<string> $cspTemplates
     *
     * @internal
     */
    public function __construct(private array $cspTemplates)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'initializeCspNonce',
            KernelEvents::RESPONSE => 'setSecurityHeaders',
        ];
    }

    public function initializeCspNonce(RequestEvent $event): void
    {
        $nonce = base64_encode(random_bytes(8));
        $event->getRequest()->attributes->set(PlatformRequest::ATTRIBUTE_CSP_NONCE, $nonce);
    }

    public function setSecurityHeaders(ResponseEvent $event): void
    {
        if (!$event->getResponse()->isSuccessful()) {
            return;
        }

        $response = $event->getResponse();
        if ($event->getRequest()->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if (!$response->headers->has(PlatformRequest::HEADER_FRAME_OPTIONS)) {
            $response->headers->set(PlatformRequest::HEADER_FRAME_OPTIONS, 'deny');
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $cspTemplate = $this->cspTemplates['default'] ?? '';

        $scopes = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        foreach ($scopes as $scope) {
            $cspTemplate = $this->cspTemplates[$scope] ?? $cspTemplate;
        }

        $cspTemplate = trim($cspTemplate);
        if ($cspTemplate !== '' && !$response->headers->has('Content-Security-Policy')) {
            $nonce = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE);

            if (\is_string($nonce)) {
                $csp = str_replace(['%nonce%', "\n", "\r"], [$nonce, ' ', ' '], $cspTemplate);
                $response->headers->set('Content-Security-Policy', $csp);
            }
        }
    }
}
