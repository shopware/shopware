<?php

namespace Shopware\CartBridge\Modifier\Struct;

use Shopware\Framework\Struct\Struct;

class ContextCartModifierFetchDefinition extends Struct
{
    /**
     * @var string[]
     */
    protected $ids;

    /**
     * @param string[] $ids
     */
    public function __construct(array $ids)
    {
    $this->ids = $ids;
    }

    /**
     * @return string[]
     */
    public function getIds(): array
    {
    return $this->ids;
    }

}