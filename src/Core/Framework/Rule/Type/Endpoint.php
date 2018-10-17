<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Type;

use Shopware\Core\Framework\Struct\Struct;

class Endpoint extends Struct
{
    /** @var string */
    protected $route;

    /** @var string */
    protected $valueField;

    /** @var string */
    protected $labelField;

    public function __construct(string $route, string $valueField, string $labelField)
    {
        $this->route = $route;
        $this->valueField = $valueField;
        $this->labelField = $labelField;
    }
}