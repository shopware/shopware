<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

class ArrayOfUuid extends Constraint
{
    public const INVALID_MESSAGE = 'The value "{{ string }}" is not a valid uuid.';
    public const INVALID_TYPE_MESSAGE = 'This value should be of type array.';
    public const INVALID_TYPE_CODE = 'FRAMEWORK__INVALID_UUID_WRITE_CONSTRAINT_VALIDATION';
}
