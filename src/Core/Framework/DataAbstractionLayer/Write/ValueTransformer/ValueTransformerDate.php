<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\ValueTransformer;

use Shopware\Core\Defaults;

class ValueTransformerDate implements ValueTransformer
{
    /**
     * {@inheritdoc}
     */
    public function transform($phpValue)
    {
        if (!$phpValue instanceof \DateTimeInterface) {
            throw new \InvalidArgumentException('Unable to format date');
        }

        return $phpValue->format(Defaults::DATE_FORMAT);
    }
}
