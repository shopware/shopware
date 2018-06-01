<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search\Query;

class TermsQuery extends Query
{
    /**
     * @var string
     */
    protected $field;

    /**
     * @var string[]|float[]|int[]
     */
    protected $value = [];

    public function __construct(string $field, array $value = [])
    {
        $this->field = $field;
        $this->value = $value;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getFields(): array
    {
        return [$this->field];
    }
}
