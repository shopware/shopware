<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\ApiValueTransformer;

class ValueTransformerRegistry
{
    /**
     * @var ValueTransformer[]
     */
    private $valueTransformers;

    public function __construct(ValueTransformer ...$valueTransformers)
    {
        $this->valueTransformers = $valueTransformers;
    }

    public function get(string $className): ValueTransformer
    {
        foreach($this->valueTransformers as $valueTransformer) {
            if($valueTransformer instanceof $className) {
                return $valueTransformer;
            }
        }

        throw new \InvalidArgumentException(sprintf('Unable to find value transformer %s', $className));
    }
}