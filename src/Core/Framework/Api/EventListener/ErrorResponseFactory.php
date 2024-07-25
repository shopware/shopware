<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use League\OAuth2\Server\Exception\OAuthServerException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @phpstan-type DefaultExceptionData array{code: string, status: string, title: string, detail: string|null, meta?: array{trace: array<int|string, mixed>, file: string, line: int, previous?: mixed}}
 *
 * @phpstan-import-type ErrorData from ShopwareHttpException as ShopwareExceptionData
 */
#[Package('core')]
class ErrorResponseFactory
{
    public function getResponseFromException(\Throwable $exception, bool $debug = false): Response
    {
        $response = new JsonResponse(
            null,
            $this->getStatusCodeFromException($exception),
            $this->getHeadersFromException($exception)
        );

        $response->setEncodingOptions($response->getEncodingOptions() | \JSON_INVALID_UTF8_SUBSTITUTE);
        $response->setData(['errors' => $this->getErrorsFromException($exception, $debug)]);

        return $response;
    }

    /**
     * @return array<DefaultExceptionData|ShopwareExceptionData>
     */
    public function getErrorsFromException(\Throwable $exception, bool $debug = false): array
    {
        if ($exception instanceof ShopwareHttpException) {
            $errors = [];
            foreach ($exception->getErrors($debug) as $error) {
                $errors[] = $error;
            }

            $errors = $this->convert($errors);

            return $errors;
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

    /**
     * @return array<string, string>
     */
    private function getHeadersFromException(\Throwable $exception): array
    {
        return $exception instanceof OAuthServerException ? $exception->getHttpHeaders() : [];
    }

    /**
     * @return DefaultExceptionData
     */
    private function convertExceptionToError(\Throwable $exception, bool $debug = false): array
    {
        $statusCode = $this->getStatusCodeFromException($exception);

        $error = [
            'code' => (string) $exception->getCode(),
            'status' => (string) $statusCode,
            'title' => (string) (Response::$statusTexts[$statusCode] ?? 'unknown status'),
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

    /**
     * @param array<string|int, mixed> $array
     *
     * @return array<string|int, mixed>
     */
    private function convert(array $array): array
    {
        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $array[$key] = $this->convert($value);
            }

            // NEXT-21735 - This is covered randomly
            // @codeCoverageIgnoreStart
            if (\is_string($value)) {
                $encodings = mb_detect_order();
                if (!ctype_print($value) && mb_strlen($value) === 16) {
                    $array[$key] = \sprintf('ATTENTION: Converted binary string by the "%s": %s', self::class, bin2hex($value));
                } elseif (!\is_bool($encodings) && !mb_detect_encoding($value, $encodings, true)) {
                    $array[$key] = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                }
            }
            // @codeCoverageIgnoreEnd

            // fix for passing resources to json encode, see https://www.php.net/manual/en/function.is-resource.php
            // the exception and consequently trace may contain arguments that are resources, like file handles
            // these resource values are now converted into a string of "<RESOURCE_TYPE>"
            $isResource = \is_resource($value) || ($value !== null && !\is_scalar($value) && !\is_array($value) && !\is_object($value));
            if ($isResource) {
                $array[$key] = \sprintf('<%s>', get_resource_type($value));
            }
        }

        return $array;
    }
}
