<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareException;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-type SearchErrorData array{
 *     status: string,
 *     code: string,
 *     title: string,
 *     detail: string,
 *     source: array{pointer: string},
 *     meta: array{parameters: array<string, mixed>},
 *     trace?: string
 * }
 */
#[Package('framework')]
class SearchRequestException extends ShopwareHttpException
{
    /**
     * @param array<string, list<\Throwable>> $exceptions
     */
    public function __construct(private array $exceptions = [])
    {
        parent::__construct('Mapping failed, got {{ numberOfFailures }} failure(s).', ['numberOfFailures' => \count($exceptions)]);
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
        if ($this->exceptions === []) {
            return;
        }

        throw $this;
    }

    /**
     * @return \Generator<SearchErrorData>
     */
    public function getErrors(bool $withTrace = false): \Generator
    {
        foreach ($this->exceptions as $pointer => $innerExceptions) {
            foreach ($innerExceptions as $exception) {
                $parameters = [];
                $code = (string) $exception->getCode();
                if ($exception instanceof ShopwareException) {
                    $parameters = $exception->getParameters();
                    $code = $exception->getErrorCode();
                }

                $error = [
                    'status' => (string) $this->getStatusCode(),
                    'code' => $code,
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
        return 'FRAMEWORK__SEARCH_REQUEST_MAPPING';
    }
}
