<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteFieldException;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationList;

class WriteConstraintViolationException extends ShopwareHttpException implements WriteFieldException, ConstraintViolationExceptionInterface
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var ConstraintViolationList
     */
    private $constraintViolationList;

    /**
     * @var int
     */
    private $statusCode;

    public function __construct(ConstraintViolationList $constraintViolationList, string $path = '', int $statusCode = Response::HTTP_BAD_REQUEST)
    {
        $this->path = $path;
        $this->constraintViolationList = $constraintViolationList;
        $this->statusCode = $statusCode;

        parent::__construct(
            'Caught {{ count }} constraint violation errors.',
            ['count' => $constraintViolationList->count()]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__WRITE_CONSTRAINT_VIOLATION';
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getViolations(): ConstraintViolationList
    {
        return $this->constraintViolationList;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function toArray(): array
    {
        $result = [];

        foreach ($this->constraintViolationList as $violation) {
            $result[] = [
                'message' => $violation->getMessage(),
                'messageTemplate' => $violation->getMessageTemplate(),
                'parameters' => $violation->getParameters(),
                'propertyPath' => $violation->getPropertyPath(),
            ];
        }

        return $result;
    }

    public function getErrors(bool $withTrace = false): \Generator
    {
        foreach ($this->getViolations() as $violation) {
            $path = $this->getPath() . $violation->getPropertyPath();
            $error = [
                'code' => $violation->getCode() ?? $this->getErrorCode(),
                'status' => (string) $this->getStatusCode(),
                'detail' => $violation->getMessage(),
                'template' => $violation->getMessageTemplate(),
                'meta' => [
                    'parameters' => $violation->getParameters(),
                ],
                'source' => [
                    'pointer' => $path,
                ],
            ];

            if ($withTrace) {
                $error['trace'] = $this->getTrace();
            }

            yield $error;
        }
    }
}
