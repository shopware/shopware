<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Type\Field;

class SelectValueFieldType extends FieldType
{
    const IDENTIFIER_MULTISELECT = 'multiselect';
    const IDENTIFIER_SINGLESELECT = 'singleselect';

    /** @var array */
    protected $values;

    public function __construct(array $values, string $identifier, FieldOperator... $operators)
    {
        $this->values = $values;
        $this->identifier = $identifier;
        $this->operators = $operators;
    }
}