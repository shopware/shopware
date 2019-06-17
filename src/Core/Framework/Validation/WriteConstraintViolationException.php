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

    public function __construct(ConstraintViolationList $constraintViolationList, string $path = '')
    {
        parent::__construct(
            'Caught {{ count }} constraint violation errors.',
            ['count' => $constraintViolationList->count()]
        );

        $this->path = $path;
        $this->constraintViolationList = $constraintViolationList;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__WRITE_CONSTRAINT_VIOLATION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getViolations(): ConstraintViolationList
    {
        return $this->constraintViolationList;
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
}
