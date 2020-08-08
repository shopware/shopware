<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use League\OAuth2\Server\Exception\OAuthServerException;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ErrorResponseFactory
{
    public function getResponseFromException(\Throwable $exception, bool $debug = false): Response
    {
        $response = new JsonResponse(
            null,
            $this->getStatusCodeFromException($exception),
            $this->getHeadersFromException($exception)
        );

        $response->setEncodingOptions($response->getEncodingOptions() | JSON_INVALID_UTF8_SUBSTITUTE);
        $response->setData(['errors' => $this->getErrorsFromException($exception, $debug)]);

        return $response;
    }

    public function getErrorsFromException(\Throwable $exception, bool $debug = false): array
    {
        if ($exception instanceof ShopwareHttpException) {
            $errors = [];
            foreach ($exception->getErrors() as $error) {
                $errors[] = $error;
            }

            return $this->convert($errors);
        }

        return [$this->convertExceptionToError($exception, $debug)];
    }

    private function getStatusCodeFromException(\Throwable $exception): int
    {
        if ($exception instanceof OAuthServerException) {
            return $exception->getHttpStatusCode();
        }

        if ($exception instanceof ShopwareHttpException || $exception instanceof HttpException) {
            return $exception->getStatusCode();
        }

        return 500;
    }

    private function getHeadersFromException(\Throwable $exception): array
    {
        return $exception instanceof OAuthServerException ? $exception->getHttpHeaders() : [];
    }

    private function convertExceptionToError(\Throwable $exception, bool $debug = false): array
    {
        $statusCode = $this->getStatusCodeFromException($exception);

        $error = [
            'code' => (string) $exception->getCode(),
            'status' => (string) $statusCode,
            'title' => Response::$statusTexts[$statusCode] ?? 'unknown status',
            'detail' => $exception->getMessage(),
        ];

        if ($exception instanceof OAuthServerException) {
            $error['title'] = $exception->getMessage();
            $error['detail'] = $exception->getHint();
        }

        if ($debug) {
            $error['meta'] = [
                'trace' => $this->convert($exception->getTrace()),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];

            if ($exception->getPrevious()) {
                $error['meta']['previous'][] = $this->convertExceptionToError($exception->getPrevious(), $debug);
            }
        }

        return $error;
    }

    private function convert(array $array): array
    {
        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $array[$key] = $this->convert($value);
            }

            if (\is_string($value)) {
                if (!ctype_print($value) && \mb_strlen($value) === 16) {
                    $array[$key] = sprintf('ATTENTION: Converted binary string by the "%s": %s', self::class, bin2hex($value));
                } elseif (!mb_detect_encoding($value, mb_detect_order(), true)) {
                    $array[$key] = utf8_encode($value);
                }
            }
        }

        return $array;
    }
}
