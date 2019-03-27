<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException;

use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class InvalidFieldException extends WriteConstraintViolationException
{
    private const CONCERN = 'validation-error';

    public function __construct(ConstraintViolationListInterface $constraintViolationList, string $path)
    {
        parent::__construct($constraintViolationList, $path, self::CONCERN);
    }
}
