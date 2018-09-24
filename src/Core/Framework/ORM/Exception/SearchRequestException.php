<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SearchRequestException extends ShopwareHttpException
{
    /**
     * @var array
     */
    private $exceptions;

    public function __construct(iterable $exceptions = [], int $code = 0, Throwable $previous = null)
    {
        $this->exceptions = $exceptions;

        parent::__construct(sprintf('Mapping failed, got %s failure(s).', \count($exceptions)), $code, $previous);
    }

    public function add(\Exception $exception, string $pointer): void
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
            /** @var \Exception $exception */
            foreach ($innerExceptions as $exception) {
                $error = [
                    'code' => (string) $exception->getCode(),
                    'status' => (string) $this->getStatusCode(),
                    'title' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                    'detail' => $exception->getMessage(),
                    'source' => ['pointer' => $pointer],
                ];

                if ($withTrace) {
                    $error['trace'] = $exception->getTraceAsString();
                }

                yield $error;
            }
        }
    }
}
