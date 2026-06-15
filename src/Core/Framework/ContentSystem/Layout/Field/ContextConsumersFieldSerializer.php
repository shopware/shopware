<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Field;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @phpstan-type ContextConsumerData array{
 *   type: 'single'|'collection',
 *   required: bool,
 *   redistribute?: bool,
 *   consumer_alias?: string|null,
 *   property_alias?: string|null
 * }
 *
 * @internal
 */
#[Package('framework')]
class ContextConsumersFieldSerializer extends AbstractFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof StorageAware) {
            throw ContentSystemException::invalidFieldType(StorageAware::class, $field::class);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();

        if (\is_array($value)) {
            $encoded = [];
            foreach ($value as $key => $consumer) {
                if ($consumer instanceof ContextConsumer) {
                    $encoded[$key] = $this->serializeContextConsumer($consumer);
                } else {
                    $encoded[$key] = $consumer;
                }
            }
            $value = $encoded;
        }

        if ($value !== null) {
            $value = Json::encode($value);
        }

        yield $field->getStorageName() => $value;
    }

    /**
     * @return array<string, ContextConsumer>|null
     */
    public function decode(Field $field, mixed $value): ?array
    {
        if (!$field instanceof ContextConsumersField) {
            throw ContentSystemException::invalidFieldType(ContextConsumersField::class, $field::class);
        }

        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        }

        if (!\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType('accepts_context', 'array', \gettype($value));
        }

        $consumers = [];
        foreach ($value as $key => $config) {
            if (!\is_array($config)) {
                continue;
            }
            $consumers[$key] = $this->deserializeContextConsumer($key, $config);
        }

        return $consumers;
    }

    /**
     * @return ContextConsumerData
     */
    public function serializeContextConsumer(ContextConsumer $consumer): array
    {
        $data = [
            'type' => $consumer->type->value,
            'required' => $consumer->required,
        ];

        if ($consumer->redistribute) {
            $data['redistribute'] = true;
        }

        if ($consumer->consumerAlias !== null) {
            $data['consumer_alias'] = $consumer->consumerAlias;
        }

        if ($consumer->propertyAlias !== null) {
            $data['property_alias'] = $consumer->propertyAlias;
        }

        return $data;
    }

    /**
     * @return list<Constraint>
     */
    public function buildConstraints(Field $field): array
    {
        $constraints = [
            new Type('array'),
            new All([
                new Collection(
                    fields: [
                        'type' => [new NotBlank(), new Choice(ContextType::values())],
                        'required' => [new Type('bool')],
                        'redistribute' => new Optional([new Type('bool')]),
                        'consumer_alias' => new Optional([new Type('string')]),
                        'property_alias' => new Optional([new Type('string')]),
                    ],
                    allowExtraFields: false,
                    allowMissingFields: false
                ),
            ]),
        ];

        if ($field->is(Required::class)) {
            $constraints[] = new NotBlank();
        }

        return $constraints;
    }

    protected function getConstraints(Field $field): array
    {
        return $this->buildConstraints($field);
    }

    /**
     * Creates ContextConsumer from config array, validates consumer_alias requires redistribute
     *
     * Accepts loose array from JSON decode - performs runtime validation.
     *
     * @param array<string, mixed> $config
     */
    private function deserializeContextConsumer(string $key, array $config): ContextConsumer
    {
        $type = ContextType::from($config['type'] ?? 'single');
        $required = $config['required'] ?? false;
        $redistribute = $config['redistribute'] ?? false;
        $consumerAlias = $config['consumer_alias'] ?? null;
        $propertyAlias = $config['property_alias'] ?? null;

        if ($consumerAlias !== null && !$redistribute) {
            throw ContentSystemException::consumerAliasWithoutRedistribute($key);
        }

        if ($propertyAlias !== null && str_contains($propertyAlias, '.')) {
            throw ContentSystemException::propertyAliasWithDotNotation($key, $propertyAlias);
        }

        return new ContextConsumer(
            type: $type,
            required: $required,
            redistribute: $redistribute,
            consumerAlias: $consumerAlias,
            propertyAlias: $propertyAlias
        );
    }
}
