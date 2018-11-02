<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\ValueTransformer;

class ValueTransformerRegistry
{
    /**
     * @var ValueTransformer[]
     */
    private $valueTransformers;

    public function __construct(iterable $valueTransformers)
    {
        $this->valueTransformers = $valueTransformers;
    }

    public function get(string $className): ValueTransformer
    {
        foreach ($this->valueTransformers as $valueTransformer) {
            if ($valueTransformer instanceof $className) {
                return $valueTransformer;
            }
        }

        throw new \InvalidArgumentException(sprintf('Unable to find value transformer %s', $className));
    }
}
