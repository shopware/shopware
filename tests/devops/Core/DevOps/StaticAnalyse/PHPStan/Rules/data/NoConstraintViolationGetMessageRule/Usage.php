<?php declare(strict_types=1);

use Symfony\Component\Validator\ConstraintViolationInterface;

function useConstraintViolation(ConstraintViolationInterface $violation): string
{
    $violation->getCode();

    return $violation->getMessage();
}
