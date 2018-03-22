<?php declare(strict_types=1);

namespace Shopware\Framework\Serializer;

use Shopware\Framework\Struct\Struct;
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
     */
    public function normalize($data, $format = null, array $context = [])
    {
        $encoder = new JsonEncode();
        $decoder = new JsonDecode(true);

        return $decoder->decode($encoder->encode($data, 'json'), 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof Struct;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class = null, $format = null, array $context = [])
    {
        if (is_string($data) && $date = $this->createDate($data)) {
            return $date;
        }

        if (!is_array($data)) {
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
    public function supportsDenormalization($data, $type, $format = null)
    {
        return is_array($data) && array_key_exists('_class', $data);
    }

    /**
     * @param array $argument
     *
     * @return bool
     */
    private function isObject(array $argument): bool
    {
        return array_key_exists('_class', $argument);
    }

    /**
     * @param string $class
     * @param array  $arguments
     *
     * @throws InvalidArgumentException
     *
     * @return object
     */
    private function createInstance(string $class, array $arguments)
    {
        try {
            $reflectionClass = $this->getReflectionClass($class);
        } catch (\ReflectionException $exception) {
            throw new InvalidArgumentException($exception->getMessage());
        }

        $instance = $reflectionClass->newInstanceWithoutConstructor();
        if (!($instance instanceof Struct)) {
            throw new InvalidArgumentException(sprintf('Unable to unserialize a non-struct class: %s', $reflectionClass->getName()));
        }

        if (!$reflectionClass->getConstructor()) {
            /* @var Struct $instance */
            $instance->assign($arguments);

            return $instance;
        }

        $constructorParams = $reflectionClass->getConstructor()->getParameters();
        if (count($constructorParams) <= 0) {
            /* @var Struct $instance */
            $instance->assign($arguments);

            return $instance;
        }
        $params = [];

        foreach ($constructorParams as $constructorParam) {
            $name = $constructorParam->getName();

            if (!array_key_exists($name, $arguments)) {
                if (!$constructorParam->isOptional()) {
                    throw new InvalidArgumentException(sprintf('Required constructor Parameter Missing: "$%s".', $name));
                }

                $params[] = $constructorParam->getDefaultValue();

                continue;
            }

            $params[] = $arguments[$name];

            unset($arguments[$name]);
        }

        $instance = $reflectionClass->newInstanceArgs($params);

        /* @var Struct $instance */
        $instance->assign($arguments);

        return $instance;
    }

    private function getReflectionClass(string $class): \ReflectionClass
    {
        if (isset($this->classes[$class])) {
            return $this->classes[$class];
        }

        return $this->classes[$class] = new \ReflectionClass($class);
    }

    private function createDate(string $date): ?\DateTime
    {
        $d = \DateTime::createFromFormat(\DateTime::ATOM, $date);

        if ($d && $d->format(\DateTime::ATOM) == $date) {
            return $d;
        }

        return null;
    }
}
