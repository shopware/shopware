<?php

namespace Shopware\Search\Sorting;

use Shopware\Framework\Struct\Struct;
use Shopware\Search\CriteriaPartInterface;

abstract class Sorting extends Struct implements CriteriaPartInterface
{
    /**
     * @var string
     */
    protected $direction;

    public function __construct(string $direction)
    {
        $this->direction = $direction;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }
}