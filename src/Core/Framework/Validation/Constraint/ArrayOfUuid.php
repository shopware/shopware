<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\Constraint;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

#[Package('core')]
class ArrayOfUuid extends Constraint
{
    final public const INVALID_MESSAGE = 'The value "{{ string }}" is not a valid uuid.';
    final public const INVALID_TYPE_MESSAGE = 'This value should be of type array.';
    final public const INVALID_TYPE_CODE = 'FRAMEWORK__INVALID_UUID_WRITE_CONSTRAINT_VALIDATION';
}
