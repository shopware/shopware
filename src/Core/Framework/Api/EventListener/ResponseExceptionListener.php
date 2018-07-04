<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use League\OAuth2\Server\Exception\OAuthServerException;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\EventListener\ExceptionListener;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ResponseExceptionListener extends ExceptionListener
{
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();

        $this->logException($exception, sprintf('Uncaught PHP Exception %s: "%s" at %s line %s', \get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()));

        if ($exception instanceof ShopwareHttpException) {
            $response = new JsonResponse(['errors' => iterator_to_array($exception->getErrors($this->debug))], $exception->getStatusCode());
        } elseif ($exception instanceof OAuthServerException) {
            $error = [
                'code' => (string) $exception->getCode(),
                'status' => (string) $exception->getHttpStatusCode(),
                'title' => $exception->getMessage(),
                'detail' => $exception->getHint(),
            ];

            $response = new JsonResponse(['errors' => [$error]], $exception->getHttpStatusCode(), $exception->getHttpHeaders());
        } else {
            $statusCode = 500;
            if ($exception instanceof HttpException) {
                $statusCode = $exception->getStatusCode();
            }

            $response = new JsonResponse(['errors' => $this->convertExceptionToError($exception)], $statusCode);
        }

        $event->setResponse($response);

        return $event;
    }

    private function convertExceptionToError(\Throwable $exception): array
    {
        $statusCode = 500;
        if ($exception instanceof HttpException) {
            $statusCode = $exception->getStatusCode();
        }

        $error = [
            'code' => (string) $exception->getCode(),
            'status' => (string) $statusCode,
            'title' => Response::$statusTexts[$statusCode] ?? 'unknown status',
            'detail' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];

        if ($this->debug) {
            $error['trace'] = $exception->getTrace();
        }

        return [$error];
    }
}
