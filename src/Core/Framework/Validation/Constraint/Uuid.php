<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

class Uuid extends Constraint
{
    public const INVALID_MESSAGE = 'The string "{{ string }}" is not a valid uuid.';
    public const INVALID_TYPE_MESSAGE = 'This value should be of type string.';
}
