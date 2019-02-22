<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use League\OAuth2\Server\Exception\OAuthServerException;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\EventListener\ExceptionListener;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class ResponseExceptionListener extends ExceptionListener
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [
                ['logKernelException'],
                ['onKernelException'],
            ],
        ];
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();

        if ($exception instanceof ShopwareHttpException) {
            $errors = iterator_to_array($exception->getErrors($this->debug));

            $response = new JsonResponse(
                ['errors' => $this->convert($errors)],
                $exception->getStatusCode()
            );
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

    public function logException(\Exception $exception, $message): void
    {
        if ($this->logger === null) {
            return;
        }

        if (!$exception instanceof ShopwareHttpException || $exception->getStatusCode() >= 500) {
            parent::logException($exception, $message);
        } else {
            $this->logger->error($message, ['exception' => $exception]);
        }
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
        ];

        if ($this->debug) {
            $error['trace'] = $this->convert($exception->getTrace());
        }

        return [$error];
    }

    private function convert(array $array): array
    {
        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $array[$key] = $this->convert($value);
            }

            if (\is_string($value)) {
                if (!ctype_print($value) && \strlen($value) === 16) {
                    $array[$key] = sprintf('ATTENTION: Converted binary string by the "%s": %s', self::class, bin2hex($value));
                } elseif (!mb_detect_encoding($value, mb_detect_order(), true)) {
                    $array[$key] = utf8_encode($value);
                }
            }
        }

        return $array;
    }
}
