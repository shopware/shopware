<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

#[Package('core')]
class JsonApiDecoder implements DecoderInterface
{
    final public const FORMAT = 'jsonapi';

    /**
     * @return array|mixed
     */
    public function decode(string $data, string $format, array $context = [])
    {
        $decodedData = (new JsonDecode([JsonDecode::ASSOCIATIVE => true]))->decode($data, 'json');

        if (!\is_array($decodedData) || !\array_key_exists('data', $decodedData)) {
            throw new UnexpectedValueException('Input not a valid JSON:API data object.');
        }

        $includes = [];
        if (\array_key_exists('included', $decodedData)) {
            $includes = $this->resolveIncludes($decodedData['included']);
        }

        if ($this->isCollection($decodedData['data'])) {
            return $this->decodeCollection($decodedData, $includes);
        }

        return $this->decodeResource($decodedData['data'], $includes);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDecoding(string $format): bool
    {
        return $format === self::FORMAT;
    }

    private function resolveRelationship(array $resource, array $includes): array
    {
        $this->validateResourceIdentifier($resource);

        \assert(\is_string($resource['id']));
        \assert(\is_string($resource['type']));
        $hash = md5(json_encode(['id' => $resource['id'], 'type' => $resource['type']], \JSON_THROW_ON_ERROR));

        if (!\array_key_exists($hash, $includes)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Resolving relationship "%s(%s)" failed due to non-existence.',
                    $resource['type'],
                    $resource['id']
                )
            );
        }

        return $includes[$hash];
    }

    private function isCollection(array $array): bool
    {
        return array_keys($array) === range(0, \count($array) - 1);
    }

    private function resolveIncludes(array $included): array
    {
        $indexed = [];

        foreach ($included as $include) {
            $this->validateResourceIdentifier($include);

            $hash = $this->getIdentifierHash($include);

            $indexed[$hash] = $this->convertToStruct($include);

            if (\array_key_exists('relationships', $include)) {
                $indexed[$hash]['relationships'] = $include['relationships'];
            }
        }

        foreach ($indexed as $hash => $include) {
            if (!\array_key_exists('relationships', $include)) {
                continue;
            }

            foreach ($include['relationships'] as $propertyName => $relationship) {
                if ($this->isCollection($relationship['data'])) {
                    $indexed[$hash][$propertyName] = $this->resolveRelationshipCollection($relationship['data'], $indexed);
                } else {
                    $indexed[$hash][$propertyName] = $this->resolveRelationship($relationship['data'], $indexed);
                }
            }

            unset($indexed[$hash]['relationships']);
        }

        return $indexed;
    }

    private function decodeResource(array $data, array $includes): array
    {
        $entity = $this->convertToStruct($data);

        if (!\array_key_exists('relationships', $data)) {
            return $entity;
        }

        if (!\is_array($data['relationships'])) {
            throw new UnexpectedValueException('Relationships of a resource must be an array of relationship links.');
        }

        foreach ($data['relationships'] as $propertyName => $relationship) {
            if (is_numeric($propertyName)) {
                throw new UnexpectedValueException('Relationships of a resource must have a valid property name.');
            }

            if (!\is_array($relationship) || !\array_key_exists('data', $relationship)) {
                throw new UnexpectedValueException('A relationship link must be an array and contain the "data" property with a single or multiple resource identifiers.');
            }

            if ($this->isCollection($relationship['data'])) {
                $entity[$propertyName] = $this->resolveRelationshipCollection($relationship['data'], $includes);
            } else {
                $entity[$propertyName] = $this->resolveRelationship($relationship['data'], $includes);
            }
        }

        return $entity;
    }

    private function convertToStruct(array $data): array
    {
        $this->validateResourceIdentifier($data);

        $entity = [
            'uuid' => $data['id'],
        ];

        if (\array_key_exists('attributes', $data)) {
            if (!\is_array($data['attributes'])) {
                throw new UnexpectedValueException('The attributes of a resource must be an array.');
            }

            $entity = array_merge($entity, $data['attributes']);
        }

        return $entity;
    }

    private function resolveRelationshipCollection(array $data, array $includes): array
    {
        $collection = [];

        foreach ($data as $relation) {
            $collection[] = $this->resolveRelationship($relation, $includes);
        }

        return $collection;
    }

    private function getIdentifierHash(array $resource): string
    {
        return md5(json_encode(['id' => $resource['id'], 'type' => $resource['type']], \JSON_THROW_ON_ERROR));
    }

    private function decodeCollection(array $data, array $includes): array
    {
        $collection = [];

        foreach ($data['data'] as $resource) {
            $collection[] = $this->decodeResource($resource, $includes);
        }

        return $collection;
    }

    private function validateResourceIdentifier($resource): void
    {
        if (\is_array($resource) && \array_key_exists('type', $resource) && \array_key_exists('id', $resource)) {
            return;
        }

        throw new UnexpectedValueException('A resource identifier must be an array containing "id" and "type".');
    }
}
