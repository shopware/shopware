<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldException;

use Symfony\Component\Validator\ConstraintViolationList;
use Throwable;

class InvalidFieldException extends ApiFieldException
{

    private const CONCERN = 'validation-error';

    /**
     * @var ConstraintViolationList
     */
    private $constraintViolationList;
    /**
     * @var string
     */
    private $path;

    public function __construct(string $path, ConstraintViolationList $constraintViolationList, $code = 0, Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Caught %s validation errors.', count($constraintViolationList)),
            $code,
            $previous
        );
        $this->constraintViolationList = $constraintViolationList;
        $this->path = $path;
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

    public function getPath(): string
    {
        return $this->path;
    }

    public function getConcern(): string
    {
        return self::CONCERN;
    }
}