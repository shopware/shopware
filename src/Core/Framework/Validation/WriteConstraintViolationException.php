<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteFieldException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationList;

#[Package('core')]
class WriteConstraintViolationException extends DataAbstractionLayerException implements WriteFieldException, ConstraintViolationExceptionInterface
{
    public function __construct(
        private readonly ConstraintViolationList $constraintViolationList,
        private string $path = '',
        int $statusCode = Response::HTTP_BAD_REQUEST
    ) {
        parent::__construct(
            $statusCode,
            'FRAMEWORK__WRITE_CONSTRAINT_VIOLATION',
            'Caught {{ count }} constraint violation errors.',
            ['count' => $constraintViolationList->count()]
        );
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

    /**
     * @return list<array{
     *     message: string|\Stringable,
     *     messageTemplate: string,
     *     parameters: array<string, string>,
     *     propertyPath: string
     * }>
     */
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
