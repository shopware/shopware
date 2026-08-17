<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Field;

use Shopware\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The content_layout `layout` column's field serializer. Both storage directions delegate to
 * {@see StoredTreeCodec}, so the wire shape of a stored forest is defined once and this class only adapts it
 * to the DAL's field contract.
 *
 * @internal
 */
#[Package('framework')]
class StoredElementListFieldSerializer extends AbstractFieldSerializer
{
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        private readonly ContentElementFieldSerializer $contentElementSerializer,
        private readonly StoredTreeCodec $treeCodec,
        private readonly LayoutDefaultSeeder $defaultSeeder,
        private readonly AttributionReconciler $attributionReconciler
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    /**
     * Seeds each element's primitive type defaults into the write payload before the resolvability gate decodes it,
     * so a tree reaching storage outside the layout mutations (direct DAL write, Sync API, import, fixtures) still
     * carries its type defaults, then reconciles each element's `attributedSpecifications` against its current
     * wiring so a stored attribution stays honest by construction (see {@see AttributionReconciler}). Runs ahead
     * of {@see PreWriteValidationEvent}.
     */
    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        $key = $field->getPropertyName();
        $value = $data[$key] ?? null;

        if ($value instanceof StoredElement) {
            $value = [$value];
        }

        if (!\is_array($value) || !\array_is_list($value)) {
            return $data;
        }

        $data[$key] = $this->defaultSeeder->seed($value);
        $data[$key] = $this->attributionReconciler->reconcile($data[$key]);

        return $data;
    }

    /**
     * The write boundary for the layout column. A raw payload is decoded into the stored model here rather than
     * passed through, so what lands in storage is what the codec produces and every later read of the column
     * decodes it again without complaint.
     *
     * That makes the codec's rules write-time rules, and its failures write-time failures: a numeric wiring key
     * throws from the {@see StoredElement} constructor and a malformed container throws from decode, neither of
     * which is an internal fault. Both are remapped to a {@see WriteConstraintViolationException} carrying the
     * layout field's path, so the caller sees the same structured rejection the constraint pass produces.
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

        if ($value instanceof StoredElement) {
            $value = [$value];
        }

        if (!\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType($field->getStorageName(), 'array', \gettype($value));
        }

        try {
            $tree = $this->tree($value);
        } catch (ContentSystemException $exception) {
            throw ContentSystemException::layoutWriteRejection($exception, $data->getKey(), $data->getValue(), $parameters->getPath());
        }

        yield $field->getStorageName() => Json::encode($this->treeCodec->encode($tree));
    }

    /**
     * @return list<StoredElement>|null
     */
    public function decode(Field $field, mixed $value): ?array
    {
        if (!$field instanceof StoredElementListField) {
            throw ContentSystemException::invalidFieldType(StoredElementListField::class, $field::class);
        }

        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        }

        if (!\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType($field->getStorageName(), 'array', \gettype($value));
        }

        return $this->treeCodec->decode($value)->roots;
    }

    /**
     * @return list<Constraint>
     */
    public function buildConstraints(Field $field): array
    {
        if (!$field instanceof StoredElementListField) {
            throw ContentSystemException::invalidFieldType(StoredElementListField::class, $field::class);
        }

        $contentElementField = new ContentElementField('', '');

        $constraints = [
            new Type('array'),
            new All(
                $this->contentElementSerializer->buildConstraints($contentElementField)
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
     * The composed style constraints derive from a runtime-mutable registry, so they must not be frozen
     * process-wide as the inherited cache would. Building fresh runs once per content_layout write, and the
     * All() wrapper still reuses that one built tree across every element in the write.
     */
    protected function getCachedConstraints(Field $field): array
    {
        return $this->getConstraints($field);
    }

    /**
     * The forest to store, from either payload shape the write path carries: elements the caller already built,
     * which are taken as they are, and raw element arrays, which the codec decodes. A payload mixing the two is
     * not a shape the write path produces, and decode rejects it rather than guessing per entry.
     *
     * @param array<array-key, mixed> $value
     */
    private function tree(array $value): StoredTree
    {
        $elements = array_filter($value, static fn (mixed $node): bool => $node instanceof StoredElement);

        if (\count($elements) === \count($value)) {
            return new StoredTree(array_values($elements));
        }

        return $this->treeCodec->decode($value);
    }
}
