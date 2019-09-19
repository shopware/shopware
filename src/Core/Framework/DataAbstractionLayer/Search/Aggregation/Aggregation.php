<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaPartInterface;
use Shopware\Core\Framework\Struct\Struct;

abstract class Aggregation extends Struct implements CriteriaPartInterface
{
    /**
     * @var string
     */
    protected $field;

    /**
     * @var string
     */
    protected $name;

    public function __construct(string $name, string $field)
    {
        $this->field = $field;
        $this->name = $name;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFields(): array
    {
        return [$this->field];
    }
}
