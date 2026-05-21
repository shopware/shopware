<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Rest;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Converts UcpExceptions on UCP routes into spec-compliant JSON error responses
 * as defined in `ucp/docs/specification/overview.md#error-handling`.
 *
 * @internal
 */
#[Package('framework')]
class UcpExceptionListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onException', 10],
        ];
    }

    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/ucp/')) {
            return;
        }

        $exception = $event->getThrowable();
        if (!$exception instanceof UcpException) {
            return;
        }

        $code = $exception->getStatusCode();
        $shortCode = strtolower(str_replace('UCP__', '', $exception->getErrorCode()));

        $body = [
            'code' => $shortCode,
            'content' => $exception->getMessage(),
        ];

        // For capability negotiation failures we wrap the response in the UCP envelope
        if (\in_array($exception->getErrorCode(), [
            UcpException::CAPABILITIES_INCOMPATIBLE,
            UcpException::VERSION_UNSUPPORTED,
        ], true)) {
            $body = [
                'ucp' => [
                    'version' => '2026-01-23',
                    'status' => 'error',
                    'capabilities' => new \stdClass(),
                ],
                'messages' => [[
                    'type' => 'error',
                    'code' => $shortCode,
                    'content' => $exception->getMessage(),
                    'severity' => 'unrecoverable',
                ]],
            ];
        }

        $response = new JsonResponse($body, $code);

        if ($code === 503) {
            $response->headers->set('Retry-After', '60');
        }

        $event->setResponse($response);
    }
}
