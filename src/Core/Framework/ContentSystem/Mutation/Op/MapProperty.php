<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * Maps one declared property to a catalogued path on the layout's root source.
 *
 * @internal
 */
#[Package('framework')]
final class MapProperty extends AbstractLayoutMutation
{
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $typeRegistry,
        private readonly AbstractContentSystemMappingCandidateRegistry $candidateRegistry,
        private readonly MappingTypeCompatibility $compatibility,
        private readonly string $rootSource,
        private readonly string $elementId,
        private readonly string $propertyKey,
        private readonly string $sourcePath,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        $node = $tree->find($this->elementId);

        if ($node === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        $this->requireRegistered($this->typeRegistry, $node->component);
        $property = $this->typeRegistry->get($node->component)->properties()[$this->propertyKey] ?? null;

        if ($property === null || !$property->mappable()) {
            throw ContentSystemException::propertyNotMappable($node->component, $this->propertyKey);
        }

        $candidate = $this->candidateRegistry->forRootSource($this->rootSource)[$this->sourcePath] ?? null;

        if ($candidate === null) {
            throw ContentSystemException::unknownMappingPath($this->sourcePath, $this->rootSource);
        }

        $declaredType = $property->type()->type();
        if (
            !$this->compatibility->permits($declaredType, $candidate->valueType)
            || !\in_array($candidate->contextType->value, $property->type()->contextTypes(), true)
        ) {
            throw ContentSystemException::mappingTypeMismatch(
                $this->propertyKey,
                \is_array($declaredType) ? implode('|', $declaredType) : $declaredType,
                $candidate->valueType,
            );
        }

        $definitions = $node->contextDefinitions;
        $consumers = $definitions->getAllConsumers();

        foreach ($consumers as $contextKey => $consumer) {
            if (($consumer->propertyAlias ?? (string) $contextKey) !== $this->propertyKey) {
                continue;
            }

            if ((string) $contextKey !== $this->propertyKey || $consumer->sourcePath === null) {
                throw ContentSystemException::propertyAliasCollision($this->propertyKey, (string) $contextKey, $this->propertyKey);
            }
        }

        $consumers[$this->propertyKey] = new ContextConsumer(
            type: $candidate->contextType,
            required: false,
            scope: ConsumerScope::Root,
            projection: $candidate->projection,
            sourcePath: $candidate->path,
        );

        $replacement = $node->withContextDefinitions(new ContextDefinitions(
            $definitions->getAllProviders(),
            $consumers,
        ));
        $this->affected = [$replacement->id];

        return $tree->replace($this->elementId, $replacement);
    }
}
