<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\IdGenerator;

class GeneratorRegistry
{
    /**
     * @var Generator[]
     */
    private $generators;

    public function __construct(iterable $valueTransformers)
    {
        $this->generators = $valueTransformers;
    }

    public function get(string $className): Generator
    {
        foreach ($this->generators as $generator) {
            if ($generator instanceof $className) {
                return $generator;
            }
        }

        throw new \InvalidArgumentException(sprintf('Unable to find generator %s', $className));
    }
}
