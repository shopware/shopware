<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp;

use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\AbstractContentSystemLayoutPresetRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\StoredDefaultProvider;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationPipeline;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationResult;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\AttachElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\AttachElements;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\DuplicateElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\InsertElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\MoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\RemoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Turns one agent edit — an operation name plus its JSON arguments — into a mutation of the given tree.
 * `set-properties` has no {@see LayoutMutation}: it replaces the element's properties and diagnoses the tree itself.
 *
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[Package('framework')]
final class LayoutEditOperationFactory
{
    public const OPERATIONS = [
        'insert-element',
        'set-properties',
        'remove-element',
        'move-element',
        'duplicate-element',
        'replace-element',
        'insert-preset',
    ];

    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $typeRegistry,
        private readonly AbstractContentSystemBindingSpecificationRegistry $bindingRegistry,
        private readonly BindingApplicator $bindingApplicator,
        private readonly AbstractContentSystemLayoutPresetRegistry $presetRegistry,
        private readonly DraftLayoutDecoder $decoder,
        private readonly MutationPipeline $pipeline,
        private readonly LayoutDiagnostics $diagnostics,
    ) {
    }

    /**
     * @param value-of<self::OPERATIONS> $operation
     * @param array<array-key, mixed> $arguments
     * @param list<ProvidedContext>|null $rootContext
     */
    public function apply(string $operation, array $arguments, StoredTree $tree, ?array $rootContext): MutationResult
    {
        if ($operation === 'set-properties') {
            return $this->setProperties(
                $tree,
                $this->requiredString($arguments, 'elementId'),
                $this->properties($arguments) ?? throw ContentSystemException::invalidFieldValueType('arguments.properties', 'object', 'null'),
                $rootContext,
            );
        }

        $mutation = match ($operation) {
            'insert-element' => $this->insertElement($arguments),
            'remove-element' => new RemoveElement($this->requiredString($arguments, 'elementId')),
            'move-element' => new MoveElement(
                $this->requiredString($arguments, 'elementId'),
                $this->optionalString($arguments, 'newParentId'),
                $this->optionalString($arguments, 'newSlot'),
                $this->optionalInt($arguments, 'index'),
            ),
            'duplicate-element' => new DuplicateElement($this->requiredString($arguments, 'elementId'), $this->optionalInt($arguments, 'index')),
            'replace-element' => new ReplaceElement(
                $this->typeRegistry,
                $this->requiredString($arguments, 'elementId'),
                $this->requiredString($arguments, 'newType'),
                $this->bindingRegistry,
                $this->bindingApplicator,
            ),
            'insert-preset' => new AttachElements(
                $this->typeRegistry,
                $this->decoder->decode($this->presetRegistry->get($this->requiredString($arguments, 'presetId'))->payload),
                $this->bindingRegistry,
                $this->bindingApplicator,
                $this->optionalString($arguments, 'parentElementId'),
                $this->optionalString($arguments, 'slot'),
            ),
        };

        return $this->pipeline->run($mutation, $tree, $rootContext);
    }

    /**
     * With properties the element is attached pre-filled, so its type defaults are seeded underneath them the way
     * {@see InsertElement} seeds a scaffold.
     *
     * @param array<array-key, mixed> $arguments
     */
    private function insertElement(array $arguments): LayoutMutation
    {
        $type = $this->requiredString($arguments, 'type');
        $parentElementId = $this->optionalString($arguments, 'parentElementId');
        $slot = $this->optionalString($arguments, 'slot');
        $index = $this->optionalInt($arguments, 'index');
        $properties = $this->properties($arguments);

        if ($properties === null) {
            return new InsertElement($this->typeRegistry, $type, $this->bindingRegistry, $this->bindingApplicator, parentElementId: $parentElementId, index: $index, slot: $slot);
        }

        if (!$this->typeRegistry->has($type)) {
            throw ContentSystemException::mutationUnknownType($type);
        }

        $element = $this->decoder->decodeOne([
            'id' => Uuid::randomHex(),
            'component' => $type,
            'properties' => [...(new StoredDefaultProvider())->forType($this->typeRegistry, $type), ...$properties],
        ]);

        return new AttachElement($this->typeRegistry, $element, $this->bindingRegistry, $this->bindingApplicator, $parentElementId, $slot, $index);
    }

    /**
     * @param array<string, mixed> $properties
     * @param list<ProvidedContext>|null $rootContext
     */
    private function setProperties(StoredTree $tree, string $elementId, array $properties, ?array $rootContext): MutationResult
    {
        $element = $tree->find($elementId) ?? throw ContentSystemException::mutationTargetNotFound($elementId);

        $updated = $tree->replace(
            $elementId,
            $element->withProperties([...$element->properties(), ...array_map(StoredValue::fromDecoded(...), $properties)]),
        );

        $analysis = $this->diagnostics->analyze($updated->roots, $rootContext);

        return MutationResult::fromParts(
            $updated,
            array_intersect_key($analysis->resolutions, [$elementId => true]),
            $analysis->report,
            [$elementId],
        );
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    private function requiredString(array $arguments, string $key): string
    {
        $value = $arguments[$key] ?? null;

        if (!\is_string($value) || $value === '') {
            throw ContentSystemException::invalidFieldValueType('arguments.' . $key, 'non-empty string', get_debug_type($value));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    private function optionalString(array $arguments, string $key): ?string
    {
        $value = $arguments[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return $this->requiredString($arguments, $key);
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    private function optionalInt(array $arguments, string $key): ?int
    {
        $value = $arguments[$key] ?? null;

        if ($value !== null && !\is_int($value)) {
            throw ContentSystemException::invalidFieldValueType('arguments.' . $key, 'integer', get_debug_type($value));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $arguments
     *
     * @return array<string, mixed>|null
     */
    private function properties(array $arguments): ?array
    {
        $value = $arguments['properties'] ?? null;

        if ($value === null) {
            return null;
        }

        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            throw ContentSystemException::invalidFieldValueType('arguments.properties', 'object', get_debug_type($value));
        }

        $properties = [];
        foreach ($value as $key => $property) {
            if (!\is_string($key)) {
                throw ContentSystemException::invalidMapKey('arguments.properties', 'int');
            }

            $properties[$key] = $property;
        }

        return $properties;
    }
}
