<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\ValueTransformer;

class ValueTransformerBoolean implements ValueTransformer
{
    /**
     * {@inheritdoc}
     */
    public function transform($phpValue)
    {
        if ($phpValue) {
            return 1;
        }

        return 0;
    }
}
