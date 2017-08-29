<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\UuidGenerator;

class GeneratorRegistry
{
    /**
     * @var Generator[]
     */
    private $valueTransformers;

    /**
     * @param Generator[] ...$valueTransformers
     */
    public function __construct(Generator ...$valueTransformers)
    {
        $this->valueTransformers = $valueTransformers;
    }

    /**
     * @param string $className
     * @return Generator
     */
    public function get(string $className): Generator
    {
        foreach($this->valueTransformers as $valueTransformer) {
            if($valueTransformer instanceof $className) {
                return $valueTransformer;
            }
        }

        throw new \InvalidArgumentException(sprintf('Unable to find generator %s', $className));
    }
}