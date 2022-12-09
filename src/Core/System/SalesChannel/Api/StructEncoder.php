<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Api;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @package core
 */
class StructEncoder
{
    private DefinitionInstanceRegistry $definitionRegistry;

    private NormalizerInterface $serializer;

    private array $protections = [];

    /**
     * @internal
     */
    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        NormalizerInterface $serializer
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->serializer = $serializer;
    }

    public function encode(Struct $struct, ResponseFields $fields): array
    {
        $array = $this->serializer->normalize($struct);

        if (!\is_array($array)) {
            throw new \RuntimeException('Normalized struct must be an array');
        }

        return $this->loop($struct, $fields, $array);
    }

    private function loop(Struct $struct, ResponseFields $fields, array $array): array
    {
        $data = $array;

        if ($struct instanceof AggregationResultCollection) {
            $mapped = [];
            foreach (\array_keys($struct->getElements()) as $index => $key) {
                if (!isset($data[$index]) || !\is_array($data[$index])) {
                    throw new \RuntimeException(\sprintf('Can not find encoded aggregation %s for data index %s', $key, $index));
                }

                $entity = $struct->get($key);
                if (!$entity instanceof Struct) {
                    throw new \RuntimeException(\sprintf('Aggregation %s is not an struct', $key));
                }

                $mapped[$key] = $this->encodeStruct($entity, $fields, $data[$index]);
            }

            return $mapped;
        }

        if ($struct instanceof EntitySearchResult) {
            $data = $this->encodeStruct($struct, $fields, $data);

            if (isset($data['elements'])) {
                $entities = [];

                foreach (\array_values($data['elements']) as $index => $value) {
                    $entity = $struct->getAt($index);
                    if (!$entity instanceof Struct) {
                        throw new \RuntimeException(\sprintf('Entity %s is not an struct', $index));
                    }

                    $entities[] = $this->encodeStruct($entity, $fields, $value);
                }
                $data['elements'] = $entities;
            }

            return $data;
        }

        if ($struct instanceof ErrorCollection) {
            return array_map(static function (Error $error) {
                return $error->jsonSerialize();
            }, $struct->getElements());
        }

        if ($struct instanceof Collection) {
            $new = [];
            foreach ($data as $index => $value) {
                $new[] = $this->encodeStruct($struct->getAt($index), $fields, $value);
            }

            return $new;
        }

        return $this->encodeStruct($struct, $fields, $data);
    }

    private function encodeStruct(Struct $struct, ResponseFields $fields, array $data): array
    {
        $alias = $struct->getApiAlias();

        foreach ($data as $property => $value) {
            if ($property === 'customFields' && $value === []) {
                $data[$property] = $value = new \stdClass();
            }

            if ($property === 'extensions') {
                $data[$property] = $this->encodeExtensions($struct, $fields, $value);

                if (empty($data[$property])) {
                    unset($data[$property]);
                }

                continue;
            }

            if (!$this->isAllowed($alias, (string) $property, $fields) && !$fields->hasNested($alias, (string) $property)) {
                unset($data[$property]);

                continue;
            }

            if (!\is_array($value)) {
                continue;
            }

            $object = $value;
            if (\array_key_exists($property, $struct->getVars())) {
                $object = $struct->getVars()[$property];
            }

            if ($object instanceof Struct) {
                $data[$property] = $this->loop($object, $fields, $value);

                continue;
            }

            // simple array of structs case
            if ($this->isStructArray($object)) {
                $array = [];
                foreach ($object as $key => $item) {
                    $array[$key] = $this->encodeStruct($item, $fields, $value[$key]);
                }

                $data[$property] = $array;

                continue;
            }

            $data[$property] = $this->encodeNestedArray($struct->getApiAlias(), (string) $property, $value, $fields);
        }

        $data['apiAlias'] = $struct->getApiAlias();

        return $data;
    }

    private function encodeNestedArray(string $alias, string $prefix, array $data, ResponseFields $fields): array
    {
        if ($prefix !== 'translated' && !$fields->hasNested($alias, $prefix)) {
            return $data;
        }

        foreach ($data as $property => &$value) {
            if ($property === 'customFields' && $value === []) {
                $value = new \stdClass();
            }

            $accessor = $prefix . '.' . $property;
            if ($prefix === 'translated') {
                $accessor = $property;
            }

            if (!$this->isAllowed($alias, $accessor, $fields)) {
                unset($data[$property]);

                continue;
            }

            if (!\is_array($value)) {
                continue;
            }

            $data[$property] = $this->encodeNestedArray($alias, $accessor, $value, $fields);
        }

        unset($value);

        return $data;
    }

    private function isAllowed(string $type, string $property, ResponseFields $fields): bool
    {
        if ($this->isProtected($type, $property)) {
            return false;
        }

        return $fields->isAllowed($type, $property);
    }

    private function isProtected(string $type, string $property): bool
    {
        $key = $type . '.' . $property;
        if (isset($this->protections[$key])) {
            return $this->protections[$key];
        }

        if (!$this->definitionRegistry->has($type)) {
            return $this->protections[$key] = false;
        }

        $definition = $this->definitionRegistry->getByEntityName($type);

        $field = $definition->getField($property);
        if (!$field) {
            return $this->protections[$key] = false;
        }

        /** @var ApiAware|null $flag */
        $flag = $field->getFlag(ApiAware::class);

        if ($flag === null) {
            return $this->protections[$key] = true;
        }

        if (!$flag->isSourceAllowed(SalesChannelApiSource::class)) {
            return $this->protections[$key] = true;
        }

        return $this->protections[$key] = false;
    }

    private function encodeExtensions(Struct $struct, ResponseFields $fields, array $value): array
    {
        $alias = $struct->getApiAlias();

        $extensions = array_keys($value);

        foreach ($extensions as $name) {
            if (!$this->isAllowed($alias, $name, $fields)) {
                unset($value[$name]);

                continue;
            }

            $extension = $struct->getExtension($name);
            if ($extension === null) {
                continue;
            }

            $value[$name] = $this->loop($extension, $fields, $value[$name]);
        }

        return $value;
    }

    private function isStructArray($object): bool
    {
        if (!\is_array($object)) {
            return false;
        }

        $values = array_values($object);
        if (!isset($values[0])) {
            return false;
        }

        return $values[0] instanceof Struct;
    }
}
