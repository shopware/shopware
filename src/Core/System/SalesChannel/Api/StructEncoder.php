<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Api;

use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\Serializer;

class StructEncoder
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    /**
     * @var ApiVersionConverter
     */
    private $apiVersionConverter;

    /**
     * @var Serializer
     */
    private $serializer;

    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        ApiVersionConverter $apiVersionConverter,
        Serializer $serializer
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->apiVersionConverter = $apiVersionConverter;
        $this->serializer = $serializer;
    }

    public function encode(Struct $struct, int $apiVersion, ResponseFields $fields): array
    {
        $data = [];

        if ($struct instanceof AggregationResultCollection) {
            foreach ($struct as $key => $item) {
                $data[$key] = $this->encodeStruct($item, $apiVersion, $fields);
            }

            return $data;
        }

        if ($struct instanceof EntitySearchResult) {
            $data = $this->encodeStruct($struct, $apiVersion, $fields);

            if (isset($data['elements'])) {
                $entities = [];
                foreach ($struct as $item) {
                    $entities[] = $this->encodeStruct($item, $apiVersion, $fields);
                }
                $data['elements'] = $entities;
            }

            return $data;
        }

        if ($struct instanceof Collection) {
            foreach ($struct as $item) {
                $data[] = $this->encodeStruct($item, $apiVersion, $fields);
            }

            return $data;
        }

        return $this->encodeStruct($struct, $apiVersion, $fields);
    }

    private function encodeStruct(Struct $struct, int $apiVersion, ResponseFields $fields)
    {
        $data = $this->serializer->normalize($struct);

        $alias = $struct->getApiAlias();

        foreach ($data as $property => $value) {
            if ($property === 'extensions') {
                $data[$property] = $this->encodeExtensions($struct, $apiVersion, $fields, $value);

                if (empty($data[$property])) {
                    unset($data[$property]);
                }

                continue;
            }

            if (!$this->isAllowed($alias, $property, $apiVersion, $fields)) {
                unset($data[$property]);

                continue;
            }

            if (!is_array($value)) {
                continue;
            }

            $object = $struct->getVars()[$property];

            if ($object instanceof Struct) {
                $data[$property] = $this->encode($object, $apiVersion, $fields);

                continue;
            }

            // simple array of structs case
            if ($this->isStructArray($object)) {
                $array = [];
                foreach ($object as $key => $item) {
                    $array[$key] = $this->encodeStruct($item, $apiVersion, $fields);
                }

                $data[$property] = $array;

                continue;
            }

            $data[$property] = $this->encodeNestedArray($struct->getApiAlias(), $property, $value, $apiVersion, $fields);
        }

        $data['apiAlias'] = $struct->getApiAlias();

        return $data;
    }

    private function encodeNestedArray(string $alias, string $prefix, array $data, int $apiVersion, ResponseFields $fields): array
    {
        if (!$fields->hasNested($alias, $prefix)) {
            return $data;
        }

        foreach ($data as $property => $value) {
            $accessor = $prefix . '.' . $property;

            if (!$this->isAllowed($alias, $accessor, $apiVersion, $fields)) {
                unset($data[$property]);

                continue;
            }

            if (!is_array($value)) {
                continue;
            }

            $data[$property] = $this->encodeNestedArray($alias, $accessor, $value, $apiVersion, $fields);
        }

        return $data;
    }

    private function isAllowed(string $type, string $property, int $apiVersion, ResponseFields $fields): bool
    {
        if ($this->isProtected($type, $property, $apiVersion)) {
            return false;
        }

        return $fields->isAllowed($type, $property);
    }

    private function isProtected(string $type, string $property, int $apiVersion): bool
    {
        if (!$this->definitionRegistry->has($type)) {
            return false;
        }

        $definition = $this->definitionRegistry->getByEntityName($type);

        $field = $definition->getField($property);
        if (!$field || !$field->is(ReadProtected::class)) {
            return !$this->apiVersionConverter->isAllowed($type, $property, $apiVersion);
        }

        /** @var ReadProtected $protection */
        $protection = $field->getFlag(ReadProtected::class);
        if (!$protection->isSourceAllowed(SalesChannelApiSource::class)) {
            return true;
        }

        return !$this->apiVersionConverter->isAllowed($type, $property, $apiVersion);
    }

    private function encodeExtensions(Struct $struct, int $apiVersion, ResponseFields $fields, array $value): array
    {
        $alias = $struct->getApiAlias();

        $extensions = array_keys($value);

        foreach ($extensions as $name) {
            if (!$this->isAllowed($alias, $name, $apiVersion, $fields)) {
                unset($value[$name]);

                continue;
            }

            $value[$name] = $this->encode($struct->getExtension($name), $apiVersion, $fields);
        }

        return $value;
    }

    private function isStructArray($object): bool
    {
        if (!is_array($object)) {
            return false;
        }

        $values = array_values($object);
        if (!isset($values[0])) {
            return false;
        }

        return $values[0] instanceof Struct;
    }
}
