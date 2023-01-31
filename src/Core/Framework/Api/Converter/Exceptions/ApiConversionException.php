<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter\Exceptions;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareException;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ApiConversionException extends ShopwareHttpException
{
    /**
     * @param array<string, \Throwable[]> $exceptions
     */
    public function __construct(private array $exceptions = [])
    {
        parent::__construct('Api Version conversion failed, got {{ numberOfFailures }} failure(s).', ['numberOfFailures' => \count($exceptions)]);
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
                $errorCode = 0;

                if ($exception instanceof ShopwareException) {
                    $parameters = $exception->getParameters();
                    $errorCode = $exception->getErrorCode();
                }

                $error = [
                    'status' => (string) $this->getStatusCode(),
                    'code' => $errorCode,
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
