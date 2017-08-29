<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\ApiValueTransformer;

class ValueTransformerBoolean implements ValueTransformer
{
    public function transform($phpValue)
    {
        if($phpValue) {
            return 1;
        }

        return 0;
    }
}