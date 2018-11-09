<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException;

use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Validation\ConstraintViolationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationInterface;

class WriteStackException extends ShopwareHttpException
{
    /**
     * @var WriteFieldException[]
     */
    private $exceptions;

    public function __construct(WriteFieldException ...$exceptions)
    {
        $this->exceptions = $exceptions;
        parent::__construct(sprintf('Mapping failed, got %s failure(s). %s', \count($exceptions), print_r($this->toArray(), true)));
    }

    /**
     * @return WriteFieldException[]
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    public function toArray(): array
    {
        $result = [];

        foreach ($this->exceptions as $exception) {
            if (!isset($result[$exception->getPath()])) {
                $result[$exception->getPath()] = [];
            }

            $result[$exception->getPath()][$exception->getConcern()] = $exception->toArray();
        }

        return $result;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrors(bool $withTrace = false): \Generator
    {
        foreach ($this->getExceptions() as $innerException) {
            if ($innerException instanceof ConstraintViolationException) {
                /** @var ConstraintViolationInterface $violation */
                foreach ($innerException->getViolations() as $violation) {
                    $path = empty($innerException->getPath()) ? $violation->getPropertyPath() : $innerException->getPath();
                    $error = [
                        'code' => $violation->getCode() ?? (string) $this->getCode(),
                        'status' => (string) $this->getStatusCode(),
                        'title' => $innerException->getConcern(),
                        'detail' => $violation->getMessage(),
                        'source' => [
                            'pointer' => $path,
                        ],
                    ];

                    if ($withTrace) {
                        $error['trace'] = $innerException->getTrace();
                    }

                    yield $error;
                }

                continue;
            }

            $error = [
                'code' => (string) $this->getCode(),
                'status' => (string) $this->getStatusCode(),
                'title' => $innerException->getConcern(),
                'detail' => $innerException->getMessage(),
                'source' => ['pointer' => $innerException->getPath()],
            ];

            if ($withTrace) {
                $error['trace'] = $innerException->getTrace();
            }

            yield $error;
        }
    }
}
