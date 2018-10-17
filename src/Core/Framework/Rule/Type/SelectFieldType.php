<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Type;

class SelectFieldType extends FieldType
{
    /** @var Endpoint */
    protected $endPoint;

    public function __construct(Endpoint $endPoint, string $identifier, FieldOperator... $operators)
    {
        $this->identifier = $identifier;
        $this->operators = $operators;
        $this->endPoint = $endPoint;
    }
}