<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Requirement\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class RequirementStackException extends ShopwareHttpException
{
    /**
     * @var RequirementException[]
     */
    private $requirements;

    public function __construct(string $method, RequirementException ...$requirements)
    {
        $this->requirements = $requirements;

        parent::__construct(
            'Could not {{ method }} plugin, got {{ failureCount }} failure(s). {{ errors }}',
            [
                'method' => $method,
                'failureCount' => \count($requirements),
                'errors' => "\n" . print_r($this->toArray(), true),
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_REQUIREMENTS_FAILED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FAILED_DEPENDENCY;
    }

    /**
     * @return RequirementException[]
     */
    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function toArray(): array
    {
        $result = [];

        foreach ($this->requirements as $exception) {
            $result[] = $exception->getMessage();
        }

        return $result;
    }

    public function getErrors(bool $withTrace = false): \Generator
    {
        foreach ($this->requirements as $exception) {
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
