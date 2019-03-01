<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Requirement\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class RequirementStackException extends ShopwareHttpException
{
    protected $code = 'PLUGIN-REQUIREMENTS-FAILED';

    /**
     * @var RequirementException[]
     */
    private $exceptions;

    public function __construct(string $method, RequirementException ...$exceptions)
    {
        $this->exceptions = $exceptions;
        parent::__construct(
            sprintf(
                "Could not %s plugin, got %d failure(s).\n%s",
                $method,
                \count($exceptions),
                print_r($this->toArray(), true)
            )
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FAILED_DEPENDENCY;
    }

    public function toArray(): array
    {
        $result = [];

        foreach ($this->exceptions as $exception) {
            $result[] = $exception->getMessage();
        }

        return $result;
    }

    public function getErrors(bool $withTrace = false): \Generator
    {
        foreach ($this->exceptions as $exception) {
            $error = [
                'code' => (string) $exception->getCode(),
                'status' => (string) $exception->getStatusCode(),
                'title' => Response::$statusTexts[$exception->getStatusCode()] ?? 'unknown status',
                'detail' => $exception->getMessage(),
            ];

            if ($withTrace) {
                $error['trace'] = $exception->getTraceAsString();
            }

            yield $error;
        }
    }
}
