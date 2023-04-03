<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

#[Package('core')]
class ConstraintViolationException extends ShopwareHttpException
{
    private readonly ConstraintViolationList $violations;

    public function __construct(
        ConstraintViolationList $violations,
        private readonly array $inputData
    ) {
        $this->mapErrorCodes($violations);

        $this->violations = $violations;

        parent::__construct('Caught {{ count }} violation errors.', ['count' => $violations->count()]);
    }

    public function getRootViolations(): ConstraintViolationList
    {
        $violations = new ConstraintViolationList();
        foreach ($this->violations as $violation) {
            if ($violation->getPropertyPath() === '') {
                $violations->add($violation);
            }
        }

        return $violations;
    }

    public function getViolations(?string $propertyPath = null): ConstraintViolationList
    {
        if (!$propertyPath) {
            return $this->violations;
        }

        $violations = new ConstraintViolationList();
        foreach ($this->violations as $violation) {
            if ($violation->getPropertyPath() === $propertyPath) {
                $violations->add($violation);
            }
        }

        return $violations;
    }

    public function getInputData(): array
    {
        return $this->inputData;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__CONSTRAINT_VIOLATION';
    }

    public function getErrors(bool $withTrace = false): \Generator
    {
        /** @var ConstraintViolation $violation */
        foreach ($this->violations as $violation) {
            $error = [
                'code' => $violation->getCode(),
                'status' => '400',
                'title' => 'Constraint violation error',
                'detail' => $violation->getMessage(),
                'source' => [
                    'pointer' => '/' . ltrim($violation->getPropertyPath(), '/'),
                ],
                'meta' => [
                    'parameters' => $violation->getParameters(),
                ],
            ];

            if ($withTrace) {
                $error['trace'] = $this->getTrace();
            }

            yield $error;
        }
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    private function mapErrorCodes(ConstraintViolationList $violations): void
    {
        /** @var ConstraintViolation $violation */
        foreach ($violations as $key => $violation) {
            if ($constraint = $violation->getConstraint()) {
                $violations->remove($key);
                $violations->add(new ConstraintViolation(
                    $violation->getMessage(),
                    $violation->getMessageTemplate(),
                    $violation->getParameters(),
                    $violation->getRoot(),
                    $violation->getPropertyPath(),
                    $violation->getInvalidValue(),
                    $violation->getPlural(),
                    'VIOLATION::' . $constraint->getErrorName($violation->getCode() ?? ''),
                    $constraint
                ));
            }
        }
    }
}
