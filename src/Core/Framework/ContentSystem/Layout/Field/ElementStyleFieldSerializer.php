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
use Symfony\Contracts\Service\ResetInterface;

/**
 * Validates and (de)serializes an element's universal ElementStyle against the style option
 * registry. Write is strict, read is lenient — see deserialize() and buildConstraints().
 *
 * @internal
 */
#[Package('framework')]
class ElementStyleFieldSerializer extends AbstractFieldSerializer implements ResetInterface
{
    /**
     * @var list<Constraint>|null
     */
    private ?array $memoizedConstraints = null;

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
     * Read-time conversion: keep only registry-known options and canonical breakpoints. No PHP option
     * classes are instantiated because a style option is data. An option absent from the registry is
     * dropped rather than erroring, so removing a provider does not break already-stored layouts. Reads
     * the precedence-resolved view, so a cross-loader name collision does not fail the read path.
     *
     * @param array<array-key, mixed> $data
     */
    public function deserialize(array $data): ElementStyle
    {
        $options = $this->registry->allResolved();
        $breakpoints = Breakpoint::values();

        $clean = [];

        foreach ($data as $optionName => $breakpointMap) {
            if (!\is_string($optionName) || !\array_key_exists($optionName, $options) || !\is_array($breakpointMap)) {
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
     * Can be called by parent serializers to compose constraints. Memoized: the registry is stable
     * within a request, so the derived Collection is built once and reused across every element.
     *
     * @return list<Constraint>
     */
    public function buildConstraints(Field $field): array
    {
        if (!$field instanceof ElementStyleField) {
            throw ContentSystemException::invalidFieldType(ElementStyleField::class, $field::class);
        }

        return $this->memoizedConstraints ??= $this->deriveConstraints();
    }

    /**
     * Drops the per-process constraint memo so a long-running runtime (worker / RoadRunner) re-derives
     * against the current registry after an app install/update has invalidated the option set.
     */
    public function reset(): void
    {
        $this->memoizedConstraints = null;
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
