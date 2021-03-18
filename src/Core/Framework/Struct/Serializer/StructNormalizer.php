<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct\Serializer;

use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class StructNormalizer implements DenormalizerInterface, NormalizerInterface
{
    /**
     * Internal cache property which contains created reflection classes
     *
     * @var \ReflectionClass[]
     */
    private $classes = [];

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $encoder = new JsonEncode();

        return (new JsonDecode([JsonDecode::ASSOCIATIVE => true]))->decode($encoder->encode($object, 'json'), 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null): bool
    {
        return $data instanceof Struct;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $type = null, $format = null, array $context = [])
    {
        if (\is_string($data) && $date = $this->createDate($data)) {
            return $date;
        }

        if (!\is_array($data)) {
            return $data;
        }

        if (!$this->isObject($data)) {
            return array_map([$this, 'denormalize'], $data);
        }

        $class = $data['_class'];
        unset($data['_class']);

        //iterate arguments to resolve other serialized objects
        $arguments = array_map(function ($argument) {
            return $this->denormalize($argument);
        }, $data);

        //create object instance
        return $this->createInstance($class, $arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null): bool
    {
        return \is_array($data) && \array_key_exists('_class', $data);
    }

    private function isObject(array $argument): bool
    {
        return isset($argument['_class']);
    }

    private function createInstance(string $class, array $arguments): Struct
    {
        try {
            $reflectionClass = $this->getReflectionClass($class);
        } catch (\ReflectionException $exception) {
            throw new InvalidArgumentException($exception->getMessage());
        }

        $struct = $reflectionClass->newInstanceWithoutConstructor();
        if (!$struct instanceof Struct) {
            throw new InvalidArgumentException(
                sprintf('Unable to unserialize a non-struct class: %s', $reflectionClass->getName())
            );
        }

        if (!$reflectionClass->getConstructor()) {
            $struct->assign($arguments);

            return $struct;
        }

        $constructorParams = $reflectionClass->getConstructor()->getParameters();
        if (\count($constructorParams) <= 0) {
            $struct->assign($arguments);

            return $struct;
        }
        $params = [];

        foreach ($constructorParams as $constructorParam) {
            $name = $constructorParam->getName();

            if (!\array_key_exists($name, $arguments)) {
                if (!$constructorParam->isOptional()) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Required constructor parameter missing: "$%s". Please check if the property is protected and not private.',
                            $name
                        )
                    );
                }

                $params[] = $constructorParam->getDefaultValue();

                continue;
            }

            $params[] = $arguments[$name];

            unset($arguments[$name]);
        }

        $struct = $reflectionClass->newInstanceArgs($params);
        if (!$struct instanceof Struct) {
            throw new InvalidArgumentException(
                sprintf('Unable to unserialize a non-struct class: %s', $reflectionClass->getName())
            );
        }
        $struct->assign($arguments);

        return $struct;
    }

    /**
     * @throws \ReflectionException
     */
    private function getReflectionClass(string $class): \ReflectionClass
    {
        if (!isset($this->classes[$class])) {
            $this->classes[$class] = new \ReflectionClass($class);
        }

        return $this->classes[$class];
    }

    private function createDate(string $date): ?\DateTimeInterface
    {
        $d = \DateTime::createFromFormat(\DateTime::ATOM, $date);

        if ($d && $d->format(\DateTime::ATOM) === $date) {
            return $d;
        }

        return null;
    }
}
