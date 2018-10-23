<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Type\Field;

use Shopware\Core\Framework\Struct\Struct;

class FieldOperator extends Struct
{
    const IDENTIFIER_IS_ONE_OF = 'isoneof';
    const IDENTIFIER_EQUALS = 'eq';
    const IDENTIFIER_NOT_EQUALS = 'neq';
    const IDENTIFIER_GREATER_THAN_EQUALS = 'gte';
    const IDENTIFIER_LOWER_THAN_EQUALS = 'lte';

    /** @var string */
    protected $identifier;

    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }
}