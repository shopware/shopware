<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteFieldException;
use Symfony\Component\Validator\ConstraintViolationList;

class WriteConstraintViolationException extends WriteFieldException implements ConstraintViolationExceptionInterface
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
     * @var string
     */
    private $concern;

    public function __construct(ConstraintViolationList $constraintViolationList, string $path = '', string $concern = '')
    {
        parent::__construct(
            'Caught {{ count }} constraint violation errors.',
            ['count' => $constraintViolationList->count()]
        );

        $this->path = $path;
        $this->constraintViolationList = $constraintViolationList;
        $this->concern = $concern;
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

    public function getConcern(): string
    {
        return ($this->concern ? ($this->concern . '-') : '') . 'violation-error';
    }
}
