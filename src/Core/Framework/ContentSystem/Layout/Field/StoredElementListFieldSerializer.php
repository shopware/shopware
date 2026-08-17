<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Field;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeConstraints;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteBoundary;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteContext;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
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
use Symfony\Component\Validator\Constraints\NotBlank;
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
        private readonly StoredTreeCodec $treeCodec,
        private readonly ViolationConstraintMapper $violationMapper,
        private readonly LayoutWriteBoundary $writeBoundary,
        private readonly StoredTreeConstraints $treeConstraints
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    /**
     * The write's first decode, and the admission that follows it. The payload is decoded into the typed tree,
     * the tree's global invariants are checked, {@see LayoutWriteBoundary} admits it, and the result is
     * re-encoded to the arrays the DAL's constraint pass and {@see encode()} expect. The boundary-processed
     * tree is then memoized on the write `Context` ({@see LayoutWriteContext}) so {@see PreWriteValidationEvent}
     * gates the very tree that is about to be stored. That removes the gate's own decode, not {@see encode()}'s:
     * a normal write decodes twice, here and again there.
     *
     * A defect raised anywhere in that chain is remapped exactly as {@see encode()} remaps a codec defect: it
     * is the caller's payload being refused at the write boundary, not an internal fault, and the DAL collects
     * a {@see WriteConstraintViolationException} thrown from normalize onto the write rather than aborting on
     * it. A raw throw would escape as an unstructured 500 instead.
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

        try {
            $tree = $this->tree($value);
            $this->rejectIllFormedTree($tree);
            $tree = $this->writeBoundary->apply($tree);
        } catch (ContentSystemException $exception) {
            throw ContentSystemException::layoutWriteRejection($exception, $key, $value, $parameters->getPath());
        }

        $data[$key] = $this->treeCodec->encode($tree);

        $this->memoize($parameters, $data['id'] ?? null, $tree);

        return $data;
    }

    /**
     * The storage encoding for the layout column. A raw payload is decoded into the stored model here rather than
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
     * The write's constraint pass, over the same wire shape {@see StoredTreeCodec} decodes: the descriptor is
     * {@see StoredTreeConstraints}'s, so what the constraint pass admits and what the codec can decode are one
     * expression rather than two that drift. The descriptor already covers the whole forest including its own
     * `All()`, so nothing wraps it here; only the field's own `Required` flag is the DAL's to add.
     *
     * @return list<Constraint>
     */
    public function buildConstraints(Field $field): array
    {
        if (!$field instanceof StoredElementListField) {
            throw ContentSystemException::invalidFieldType(StoredElementListField::class, $field::class);
        }

        $constraints = $this->treeConstraints->build();

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

    /**
     * The tree-global invariants the codec cannot see, checked before the boundary rebuilds anything: a
     * forest that repeats an element id addresses two nodes under one id, and every later stage — the
     * boundary's own walks included — would silently pick one of them.
     */
    private function rejectIllFormedTree(StoredTree $tree): void
    {
        $violations = $tree->validate();

        if ($violations === []) {
            return;
        }

        throw ContentSystemException::invalidLayoutStructure(
            $this->violationMapper->toConstraintViolationList($violations)
        );
    }

    /**
     * Hands the boundary-processed tree to the write's {@see LayoutWriteContext}, creating it on the first
     * layout row of the write. The id is already minted at this point: the extractor normalizes every
     * primary-key field before any other field.
     */
    private function memoize(WriteParameterBag $parameters, mixed $id, StoredTree $tree): void
    {
        if (!\is_string($id)) {
            throw ContentSystemException::invalidFieldValueType('id', 'string', get_debug_type($id));
        }

        $context = $parameters->getContext()->getContext();
        $memo = $context->getExtension(LayoutWriteContext::EXTENSION_NAME);

        if (!$memo instanceof LayoutWriteContext) {
            $memo = new LayoutWriteContext();
            $context->addExtension(LayoutWriteContext::EXTENSION_NAME, $memo);
        }

        $memo->remember($parameters->getDefinition()->getEntityName(), $id, $tree);
    }
}
