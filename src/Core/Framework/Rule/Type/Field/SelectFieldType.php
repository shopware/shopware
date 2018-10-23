<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Type\Field;

class SelectFieldType extends SelectValueFieldType
{
    /** @var Endpoint */
    protected $endPoint;

    public function __construct(Endpoint $endPoint, string $identifier, FieldOperator... $operators)
    {
        parent::__construct([], $identifier, ... $operators);
        $this->endPoint = $endPoint;
    }
}