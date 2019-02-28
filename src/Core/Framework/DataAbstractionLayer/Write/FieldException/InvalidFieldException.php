<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException;

use Shopware\Core\Framework\Validation\ConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class InvalidFieldException extends ConstraintViolationException
{
    private const CONCERN = 'validation-error';

    public function __construct(ConstraintViolationListInterface $constraintViolationList, string $path, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($constraintViolationList, $path, $code, $previous, self::CONCERN);
    }
}
