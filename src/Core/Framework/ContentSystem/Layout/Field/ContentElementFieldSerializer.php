<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Field;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
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
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @phpstan-import-type ContextConsumerData from ContextConsumersFieldSerializer
 * @phpstan-import-type DataRequirementData from DataRequirementsFieldSerializer
 *
 * @phpstan-type ContentElementData array{
 *   id: string,
 *   component: string,
 *   properties: array<string, mixed>,
 *   data_requirements?: array<string, DataRequirementData>,
 *   slots?: array<string, list<array<string, mixed>>>,
 *   provides_context?: array<string, array<string, mixed>>,
 *   accepts_context?: array<string, ContextConsumerData>
 * }
 *
 * @internal
 */
#[Package('framework')]
class ContentElementFieldSerializer extends AbstractFieldSerializer
{
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        private readonly DataRequirementsFieldSerializer $dataRequirementsSerializer,
        private readonly ContextProvidersFieldSerializer $contextProvidersSerializer,
        private readonly ContextConsumersFieldSerializer $contextConsumersSerializer,
        private readonly ElementSlotsFieldSerializer $elementSlotsSerializer
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    /**
     * Encodes a ContentElement for database storage.
     *
     * The serializer is format-agnostic — it serializes whatever properties are present on
     * the element at call time. In the normal write path (Admin API saving a layout), the
     * element has not been hydrated, so properties contain only static/config values.
     * FQCN-typed values exist as instructions in data_requirements or accepts_context,
     * and are materialized into properties only at hydration time.
     */
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

        if ($value === null) {
            yield $field->getStorageName() => null;

            return;
        }

        if ($value instanceof ContentElement) {
            $value = $this->serializeContentElement($value);
        }

        if (!\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType('element', 'array|ContentElement', \gettype($value));
        }

        yield $field->getStorageName() => Json::encode($value);
    }

    /**
     * Decodes a ContentElement from database JSON.
     *
     * The returned element is in the "storage" lifecycle stage: properties contain only
     * static/config values. FQCN-typed property values are not present — they exist as
     * instructions in data_requirements and accepts_context, awaiting hydration.
     */
    public function decode(Field $field, mixed $value): ?ContentElement
    {
        if (!$field instanceof ContentElementField) {
            throw ContentSystemException::invalidFieldType(ContentElementField::class, $field::class);
        }

        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        }

        if (!\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType('element', 'array', \gettype($value));
        }

        return $this->decodeElement($value);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function decodeElement(array $data): ContentElement
    {
        if (!\array_key_exists('id', $data) || !\is_string($data['id'])) {
            throw ContentSystemException::invalidFieldValueType('id', 'string', \gettype($data['id'] ?? null));
        }

        if (!\array_key_exists('component', $data) || !\is_string($data['component'])) {
            throw ContentSystemException::invalidFieldValueType('component', 'string', \gettype($data['component'] ?? null));
        }

        $dataRequirementsField = new DataRequirementsField('data_requirements', 'dataRequirements');
        $dataRequirements = \array_key_exists('data_requirements', $data) && \is_array($data['data_requirements'])
            ? $this->dataRequirementsSerializer->decode($dataRequirementsField, $data['data_requirements'])
            : [];

        $contextProvidersField = new ContextProvidersField('provides_context', 'providesContext');
        $contextConsumersField = new ContextConsumersField('accepts_context', 'acceptsContext');

        // Null default matches decode() return type; ?? [] used below for type safety
        $providers = \array_key_exists('provides_context', $data) && \is_array($data['provides_context'])
            ? $this->contextProvidersSerializer->decode($contextProvidersField, $data['provides_context'])
            : null;

        $consumers = \array_key_exists('accepts_context', $data) && \is_array($data['accepts_context'])
            ? $this->contextConsumersSerializer->decode($contextConsumersField, $data['accepts_context'])
            : null;

        $contextDefinitions = new ContextDefinitions($providers ?? [], $consumers ?? []);

        // Lazy-loaded to break circular dependency
        $slotsField = new ElementSlotsField('slots', 'slots');
        $slots = \array_key_exists('slots', $data) && \is_array($data['slots'])
            ? ($this->elementSlotsSerializer->decode($slotsField, $data['slots']) ?? [])
            : [];

        return new ContentElement(
            $data['id'],
            $data['component'],
            $dataRequirements ?? [],
            $data['properties'] ?? [],
            $slots,
            $contextDefinitions
        );
    }

    /**
     * @return ContentElementData
     */
    public function serializeContentElement(ContentElement $element): array
    {
        $array = [
            'id' => $element->getId(),
            'component' => $element->getComponent(),
            'properties' => $this->serializeProperties($element->getProperties()),
        ];

        $dataRequirements = $element->getDataRequirements();
        if ($dataRequirements !== []) {
            $serializedRequirements = array_map(function ($requirement) {
                return $this->dataRequirementsSerializer->serializeDataRequirement($requirement);
            }, $dataRequirements);
            $array['data_requirements'] = $serializedRequirements;
        }

        if ($element->hasSlots()) {
            $array['slots'] = $this->elementSlotsSerializer->serializeSlots($element->getSlots());
        }

        $providers = $element->getProvidesContext();
        $consumers = $element->getAcceptsContext();

        if ($providers !== []) {
            $serializedProviders = [];
            foreach ($providers as $key => $provider) {
                $serializedProviders[$key] = $this->contextProvidersSerializer->serializeContextProvider($provider);
            }

            $array['provides_context'] = $serializedProviders;
        }

        if ($consumers !== []) {
            $serializedConsumers = array_map(function ($consumer) {
                return $this->contextConsumersSerializer->serializeContextConsumer($consumer);
            }, $consumers);
            $array['accepts_context'] = $serializedConsumers;
        }

        return $array;
    }

    /**
     * Build constraints for this field. Can be called by parent serializers to compose constraints.
     *
     * @return list<Constraint>
     */
    public function buildConstraints(Field $field): array
    {
        if (!$field instanceof ContentElementField) {
            throw ContentSystemException::invalidFieldType(ContentElementField::class, $field::class);
        }

        $nestedFields = $field->getNamedPropertyMapping();

        $dataRequirementsConstraints = $this->dataRequirementsSerializer->buildConstraints($nestedFields['dataRequirements']);
        $slotsConstraints = $this->elementSlotsSerializer->buildConstraints($nestedFields['slots']);
        $providesContextConstraints = $this->contextProvidersSerializer->buildConstraints($nestedFields['providesContext']);
        $acceptsContextConstraints = $this->contextConsumersSerializer->buildConstraints($nestedFields['acceptsContext']);

        $dataRequirementsField = $nestedFields['dataRequirements']->is(Required::class)
            ? $dataRequirementsConstraints
            : new Optional($dataRequirementsConstraints);

        $slotsField = $nestedFields['slots']->is(Required::class)
            ? $slotsConstraints
            : new Optional($slotsConstraints);

        $providesContextField = $nestedFields['providesContext']->is(Required::class)
            ? $providesContextConstraints
            : new Optional($providesContextConstraints);

        $acceptsContextField = $nestedFields['acceptsContext']->is(Required::class)
            ? $acceptsContextConstraints
            : new Optional($acceptsContextConstraints);

        $constraints = [
            new Type('array'),
            new Collection(
                fields: [
                    'id' => [new NotBlank(), new Type('string')],
                    'component' => [new NotBlank(), new Type('string')],
                    'properties' => new Optional([new Type('array')]),
                    'data_requirements' => $dataRequirementsField,
                    'slots' => $slotsField,
                    'provides_context' => $providesContextField,
                    'accepts_context' => $acceptsContextField,
                ],
                allowExtraFields: false,
                allowMissingFields: false
            ),
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
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    private function serializeProperties(array $properties): array
    {
        $serialized = [];

        foreach ($properties as $key => $value) {
            if (\is_object($value) && method_exists($value, 'toArray')) {
                $serialized[$key] = $value->toArray();
                continue;
            }

            $serialized[$key] = $value;
        }

        return $serialized;
    }
}
