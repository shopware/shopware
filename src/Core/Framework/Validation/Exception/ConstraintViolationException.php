<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @phpstan-type ConstraintErrorData array{
 *     code: string|null,
 *     status: '400',
 *     title: 'Constraint violation error',
 *     detail: string|\Stringable,
 *     meta: array{parameters: array<string, mixed>},
 *     source: array{pointer: string},
 *     trace?: array<int, mixed>
 * }
 */
#[Package('framework')]
class ConstraintViolationException extends ShopwareHttpException
{
    private readonly ConstraintViolationList $violations;

    /**
     * @param array<array-key, mixed> $inputData
     */
    public function __construct(
        ConstraintViolationList $violations,
        private readonly array $inputData
    ) {
        $this->mapErrorCodes($violations);

        $this->violations = $violations;

        parent::__construct('Caught {{ count }} violation errors.', ['count' => $violations->count()]);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement as it is unused
     */
    public function getRootViolations(): ConstraintViolationList
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.8.0.0'),
        );

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

    /**
     * Is used in Twig template files
     *
     * @return array<array-key, mixed>
     */
    public function getInputData(): array
    {
        return $this->inputData;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__CONSTRAINT_VIOLATION';
    }

    /**
     * @return \Generator<ConstraintErrorData>
     */
    public function getErrors(bool $withTrace = false): \Generator
    {
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
        foreach ($violations as $key => $violation) {
            if ($constraint = $violation->getConstraint()) {
                try {
                    $errorCode = $constraint->getErrorName($violation->getCode() ?? '');
                } catch (\InvalidArgumentException) {
                    $errorCode = $violation->getCode();
                }

                $violations->remove($key);
                $violations->add(new ConstraintViolation(
                    $violation->getMessage(),
                    $violation->getMessageTemplate(),
                    $violation->getParameters(),
                    $violation->getRoot(),
                    $violation->getPropertyPath(),
                    $violation->getInvalidValue(),
                    $violation->getPlural(),
                    'VIOLATION::' . $errorCode,
                    $constraint
                ));
            }
        }
    }
}
