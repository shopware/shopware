<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Field;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionConstraintDeriver;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Validates and (de)serializes an element's universal ElementStyle. The write path is strict and
 * derives its constraints from the style option registry; the read path is registry-free and keeps
 * unknown options verbatim — see deserialize() and buildConstraints().
 *
 * @internal
 */
#[Package('framework')]
class ElementStyleFieldSerializer extends AbstractFieldSerializer
{
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        private readonly AbstractContentSystemStyleOptionRegistry $registry,
        private readonly StyleOptionConstraintDeriver $deriver,
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof ElementStyleField) {
            throw ContentSystemException::invalidFieldType(ElementStyleField::class, $field::class);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();

        if ($value === null) {
            yield $field->getStorageName() => null;

            return;
        }

        if ($value instanceof ElementStyle) {
            $value = $value->toArray();
        }

        if (!\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType('style', 'array|ElementStyle', \gettype($value));
        }

        yield $field->getStorageName() => Json::encode($value);
    }

    public function decode(Field $field, mixed $value): ?ElementStyle
    {
        if (!$field instanceof ElementStyleField) {
            throw ContentSystemException::invalidFieldType(ElementStyleField::class, $field::class);
        }

        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        }

        if (!\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType('style', 'array', \gettype($value));
        }

        return $this->deserialize($value);
    }

    /**
     * Read-time conversion: registry-free structural cleaning only. An option key must be a string, its
     * value a breakpoint map, each breakpoint a canonical Breakpoint::values() key, each value a scalar;
     * an empty map is dropped. Unknown option names ride through verbatim — the registry is consulted only
     * on the write path, so removing a provider does not break an already-stored layout's read.
     *
     * @param array<array-key, mixed> $data
     */
    public function deserialize(array $data): ElementStyle
    {
        $breakpoints = Breakpoint::values();

        $clean = [];

        foreach ($data as $optionName => $breakpointMap) {
            if (!\is_string($optionName) || !\is_array($breakpointMap)) {
                continue;
            }

            $cleanMap = [];
            foreach ($breakpointMap as $breakpoint => $value) {
                if (!\in_array($breakpoint, $breakpoints, true) || !\is_scalar($value)) {
                    continue;
                }

                $cleanMap[$breakpoint] = $value;
            }

            if ($cleanMap !== []) {
                $clean[$optionName] = $cleanMap;
            }
        }

        return new ElementStyle($clean);
    }

    /**
     * Can be called by parent serializers to compose constraints. Derived fresh on each call from the
     * registry's current option set, so an app install/update/activation that changed the set is reflected
     * on the next write without a process restart. The parent serializer reuses one built Collection across
     * every element within a single write.
     *
     * @return list<Constraint>
     */
    public function buildConstraints(Field $field): array
    {
        if (!$field instanceof ElementStyleField) {
            throw ContentSystemException::invalidFieldType(ElementStyleField::class, $field::class);
        }

        return $this->deriveConstraints();
    }

    protected function getConstraints(Field $field): array
    {
        return $this->buildConstraints($field);
    }

    /**
     * @return list<Constraint>
     */
    private function deriveConstraints(): array
    {
        $optionFields = [];

        foreach ($this->registry->all() as $name => $specification) {
            $valueConstraints = $this->deriver->derive($specification->valueType());

            // One Optional per canonical breakpoint; allowExtraFields rejects any unknown breakpoint key
            $breakpointFields = [];
            foreach (Breakpoint::values() as $breakpoint) {
                $breakpointFields[$breakpoint] = new Optional($valueConstraints);
            }

            $optionFields[$name] = new Optional([
                new Type('array'),
                new Collection(
                    fields: $breakpointFields,
                    allowExtraFields: false,
                    allowMissingFields: false,
                ),
            ]);
        }

        return [
            new Type('array'),
            new Collection(
                fields: $optionFields,
                allowExtraFields: false,
                allowMissingFields: false,
            ),
        ];
    }
}
