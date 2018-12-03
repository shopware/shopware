<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

class ArrayOfUuid extends Constraint
{
    const INVALID_MESSAGE = 'The value "{{ string }}" is not a valid uuid.';
    const INVALID_TYPE_MESSAGE = 'This value should be of type array.';
}
