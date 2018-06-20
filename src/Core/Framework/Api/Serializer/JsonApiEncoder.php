<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

use Shopware\Core\Framework\Api\Exception\MissingDataException;
use Shopware\Core\Framework\Api\Exception\MissingValueException;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\Field;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Write\Flag\Extension;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\Struct\Serializer\StructDecoder;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Exception\BadMethodCallException;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

class JsonApiEncoder implements EncoderInterface
{
    public const FORMAT = 'jsonapi';

    /**
     * @var StructDecoder
     */
    private $structDecoder;

    /**
     * Properties that should not appear in the attributes of a resource
     *
     * @var array
     */
    private static $ignoredAttributes = [
        'id' => 1,
        '_class' => 1,
        'translations' => 1,
    ];

    public function __construct(StructDecoder $structDecoder)
    {
        $this->structDecoder = $structDecoder;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsEncoding($format): bool
    {
        return $format === self::FORMAT;
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data, $format, array $context = [])
    {
        $data = $this->structDecoder->decode($data, 'struct');

        if (!is_iterable($data)) {
            throw new UnexpectedValueException('Input is not iterable.');
        }

        if (!array_key_exists('uri', $context)) {
            throw new BadMethodCallException('The context key "uri" is required.');
        }

        if (!array_key_exists('definition', $context)) {
            throw new BadMethodCallException(sprintf('The context key "definition" is required and must be an instance of %s.', EntityDefinition::class));
        }

        if (!array_key_exists('basic', $context)) {
            throw new BadMethodCallException('The context key "basic" is required to indicate which type of struct should be encoded.');
        }

        $response = [];

        if (array_key_exists('data', $context)) {
            $response = array_merge($response, $context['data']);
        }

        if (empty($data)) {
            $response['data'] = [];

            return json_encode($response);
        }

        if ($this->isCollection($data)) {
            $response = array_merge($response, $this->encodeCollection($data, $context));

            $primaryResourcesHashes = array_map(function (array $resource) {
                return $this->getResourceHash($resource);
            }, $response['data']);
        } else {
            $response = array_merge($response, $this->encodeEntity($data, $context));
            $primaryResourcesHashes = [$this->getResourceHash($response['data'])];
        }

        if (isset($response['included']) && \count($response)) {
            // reduce includes by removing primary resources
            $response['included'] = array_values(array_diff_key($response['included'], array_flip($primaryResourcesHashes)));
        }

        return json_encode($response);
    }

    /**
     * @param mixed $data
     * @param array $context
     *
     * @return array
     */
    public function encodeEntity($data, array $context = []): array
    {
        $attributes = [];
        $relationships = [];
        $includes = [];

        $objectContextUri = $context['uri'] . '/' . $this->camelCaseToSnailCase($context['definition']::getEntityName()) . '/' . $this->getIdentifier($data);

        /** @var array $fields */
        $fields = $context['definition']::getFields()->getElements();

        $missingProperties = [];

        foreach ($fields as $field) {
            $key = $field->getPropertyName();

            if (isset(self::$ignoredAttributes[$key])) {
                continue;
            }

            try {
                $value = $this->getValue($field, $data);
            } catch (MissingValueException $exception) {
                if (!$field instanceof FkField && $field->is(Required::class)) {
                    $missingProperties[] = $exception->getFieldName();
                }

                $value = $this->getDefaultValue($field);
            }

            if ($field instanceof ManyToOneAssociationField) {
                $relationships[$key] = [
                    'data' => null,
                    'links' => [
                        'related' => $objectContextUri . '/' . $this->camelCaseToSnailCase($key),
                    ],
                ];

                if (!$value) {
                    continue;
                }

                $subContext = $context;
                $subContext['definition'] = $field->getReferenceClass();
                $subContext['basic'] = true;

                $relationship = $this->extractRelationship($value, $subContext['definition']);
                $relationships[$key]['data'] = $relationship;

                $encoded = $this->encodeEntity($value, $subContext);
                $includes[$this->getResourceHash($relationship)] = $encoded['data'];

                if (\count($encoded['included'])) {
                    $includes = array_merge($includes, $encoded['included']);
                }
                continue;
            }

            if ($field instanceof ManyToManyAssociationField || $field instanceof OneToManyAssociationField) {
                $relationships[$key] = [
                    'data' => [],
                    'links' => [
                        'related' => $objectContextUri . '/' . $this->camelCaseToSnailCase($key),
                    ],
                ];

                if (!$value || \count($value) === 0) {
                    continue;
                }

                foreach ($value as $resource) {
                    $subContext = $context;

                    if ($field instanceof OneToManyAssociationField) {
                        $subContext['definition'] = $field->getReferenceClass();
                    } else {
                        $subContext['definition'] = $field->getReferenceDefinition();
                    }

                    $subContext['basic'] = true;

                    $relationship = $this->extractRelationship($resource, $subContext['definition']);
                    $relationships[$key]['data'][] = $relationship;

                    $encoded = $this->encodeEntity($resource, $subContext);
                    $includes[$this->getResourceHash($relationship)] = $encoded['data'];

                    if (\count($encoded['included'])) {
                        $includes = array_merge($includes, $encoded['included']);
                    }
                }
                continue;
            }

            $attributes[$key] = $value;
        }

        if (\count($missingProperties) > 0) {
            throw new MissingDataException($missingProperties);
        }

        $context['uri'] = $objectContextUri;

        $object = [
            'id' => $this->getIdentifier($data),
            'type' => $context['definition']::getEntityName(),
            'links' => [
                'self' => $context['uri'],
            ],
        ];

        if (\count($attributes)) {
            $object['attributes'] = $attributes;
        }

        if (\count($relationships)) {
            $object['relationships'] = $relationships;
        }

        $response = [
            'data' => $object,
            'included' => $includes,
        ];

        return $response;
    }

    private function isCollection(array $array): bool
    {
        return array_keys($array) === range(0, \count($array) - 1);
    }

    /**
     * @param array $data
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    private function getIdentifier(array $data): string
    {
        if (!isset($data['id'])) {
            throw new UnexpectedValueException('Could not determine identifier for object.');
        }

        return $data['id'];
    }

    /**
     * @param mixed                   $value
     * @param string|EntityDefinition $definition
     *
     * @return array
     */
    private function extractRelationship($value, string $definition): array
    {
        return [
            'id' => $this->getIdentifier($value),
            'type' => $definition::getEntityName(),
        ];
    }

    /**
     * @param mixed $data
     * @param array $context
     *
     * @return array
     */
    private function encodeCollection($data, array $context): array
    {
        $response = [
            'data' => [],
            'included' => [],
        ];

        foreach ($data as $resource) {
            $resource = $this->encodeEntity($resource, $context);

            $response['data'][] = $resource['data'];

            foreach ($resource['included'] as $include) {
                $key = $this->getResourceHash($include);

                if (isset($response['included'][$key])) {
                    continue;
                }

                $response['included'][$key] = $include;
            }
        }

        return $response;
    }

    private function camelCaseToSnailCase(string $input): string
    {
        $input = str_replace('_', '-', $input);

        return ltrim(strtolower(preg_replace('/[A-Z]/', '-$0', $input)), '-');
    }

    private function getResourceHash(array $resource): string
    {
        return md5(json_encode(['id' => $resource['id'], 'type' => $resource['type']]));
    }

    /**
     * @param Field $field
     * @param array $data
     *
     * @return mixed
     */
    private function getValue(Field $field, array $data)
    {
        $name = $field->getPropertyName();
        if (isset($data[$name]) || array_key_exists($name, $data)) {
            return $data[$name];
        }

        if (!$field->is(Extension::class)) {
            throw new MissingValueException($name);
        }
        if (!array_key_exists('extensions', $data)) {
            throw new RuntimeException(sprintf('Expected data container to contain key "extensions". It is required for field "%s".', $name));
        }

        if (!array_key_exists($name, $data['extensions'])) {
            throw new MissingValueException(sprintf('extensions.%s', $name));
        }

        return $data['extensions'][$name];
    }

    private function getDefaultValue(Field $field): ?array
    {
        if ($field instanceof ManyToManyAssociationField || $field instanceof OneToManyAssociationField) {
            return [];
        }

        return null;
    }
}
