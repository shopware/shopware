<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter\Exceptions;

use Shopware\Core\Framework\ShopwareException;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class ApiConversionException extends ShopwareHttpException
{
    /**
     * @var array[string]\Throwable[]
     */
    private $exceptions;

    public function __construct(array $exceptions = [])
    {
        $this->exceptions = $exceptions;

        parent::__construct('Api Version conversion failed, got {{ numberOfFailures }} failure(s).', ['numberOfFailures' => count($exceptions)]);
    }

    public function add(\Throwable $exception, string $pointer): void
    {
        $this->exceptions[$pointer][] = $exception;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function tryToThrow(): void
    {
        if (empty($this->exceptions)) {
            return;
        }

        throw $this;
    }

    public function getErrors(bool $withTrace = false): \Generator
    {
        foreach ($this->exceptions as $pointer => $innerExceptions) {
            /** @var ShopwareException $exception */
            foreach ($innerExceptions as $exception) {
                $parameters = [];
                if ($exception instanceof ShopwareException) {
                    $parameters = $exception->getParameters();
                }

                $error = [
                    'status' => (string) $this->getStatusCode(),
                    'code' => $exception->getErrorCode(),
                    'title' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                    'detail' => $exception->getMessage(),
                    'source' => ['pointer' => $pointer],
                    'meta' => [
                        'parameters' => $parameters,
                    ],
                ];

                if ($withTrace) {
                    $error['trace'] = $exception->getTraceAsString();
                }

                yield $error;
            }
        }
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__API_VERSION_CONVERSION';
    }
}
