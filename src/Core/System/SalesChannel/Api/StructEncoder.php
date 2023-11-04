<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Api;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\Entity\DefinitionRegistryChain;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Package('core')]
class StructEncoder
{
    /**
     * @var array<string, bool>
     */
    private array $protections = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionRegistryChain $registry,
        private readonly NormalizerInterface $serializer
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function encode(Struct $struct, ResponseFields $fields): array
    {
        $array = $this->serializer->normalize($struct);

        if (!\is_array($array)) {
            throw new \RuntimeException('Normalized struct must be an array');
        }

        return $this->loop($struct, $fields, $array);
    }

    /**
     * @param array<mixed> $array
     *
     * @return array<mixed>
     */
    private function loop(Struct $struct, ResponseFields $fields, array $array): array
    {
        $data = $array;

        if ($struct instanceof AggregationResultCollection) {
            $mapped = [];
            /**
             * @var int $index
             * @var string $key
             */
            foreach (\array_keys($struct->getElements()) as $index => $key) {
                if (!isset($data[$index]) || !\is_array($data[$index])) {
                    throw new \RuntimeException(\sprintf('Can not find encoded aggregation %s for data index %d', $key, $index));
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

                /**
                 * @var int $index
                 */
                foreach (\array_values($data['elements']) as $index => $value) {
                    $entity = $struct->getAt($index);
                    if (!$entity instanceof Struct) {
                        throw new \RuntimeException(\sprintf('Entity at index %d is not an struct', $index));
                    }

                    $entities[] = $this->encodeStruct($entity, $fields, $value);
                }
                $data['elements'] = $entities;
            }

            return $data;
        }

        if ($struct instanceof ErrorCollection) {
            return array_map(static fn (Error $error) => $error->jsonSerialize(), $struct->getElements());
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

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private function encodeStruct(Struct $struct, ResponseFields $fields, array $data, ?string $alias = null): array
    {
        $alias = $alias ?? $struct->getApiAlias();

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

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
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

        if (!$this->registry->has($type)) {
            return $this->protections[$key] = false;
        }

        $definition = $this->registry->getByEntityName($type);

        $field = $definition->getField($property);

        if ($property === 'translated') {
            return $this->protections[$key] = false;
        }

        if (!$field) {
            return $this->protections[$key] = true;
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

    /**
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    private function encodeExtensions(Struct $struct, ResponseFields $fields, array $value): array
    {
        $alias = $struct->getApiAlias();

        $extensions = array_keys($value);

        foreach ($extensions as $name) {
            if ($name === 'search') {
                if (!$fields->isAllowed($alias, $name)) {
                    unset($value[$name]);

                    continue;
                }

                $value[$name] = $this->encodeNestedArray($alias, 'search', $value[$name], $fields);

                continue;
            }
            if ($name === 'foreignKeys') {
                // loop the foreign keys array with the api alias of the original struct to scope the values within the same entity definition
                $extension = $struct->getExtension('foreignKeys');

                if (!$extension instanceof Struct) {
                    unset($value[$name]);

                    continue;
                }

                $value[$name] = $this->encodeStruct($extension, $fields, $value['foreignKeys'], $alias);

                // only api alias inside, remove it
                if (\count($value[$name]) === 1) {
                    unset($value[$name]);
                }

                continue;
            }

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

    /**
     * @param array|mixed $object
     */
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
