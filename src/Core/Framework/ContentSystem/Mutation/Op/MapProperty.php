<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\StorefrontContext\StorefrontContextLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\StorefrontContextMappingCandidateProvider;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
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
        private readonly StoredMappingInspector $mappingInspector,
        private readonly string $rootSource,
        private readonly string $elementId,
        private readonly string $propertyKey,
        private readonly MappingSourceReference $source,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        $node = $tree->find($this->elementId);

        if ($node === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        $this->requireRegistered($this->typeRegistry, $node->component);
        $candidate = $this->mappingInspector->admitProperty($node->component, $this->propertyKey, $this->source, $this->rootSource);
        if ($candidate instanceof ContentSystemException) {
            throw $candidate;
        }

        $definitions = $node->contextDefinitions;
        $consumers = $definitions->getAllConsumers();

        foreach ($consumers as $contextKey => $consumer) {
            if (($consumer->propertyAlias ?? (string) $contextKey) !== $this->propertyKey) {
                continue;
            }

            if ((string) $contextKey !== $this->propertyKey || $consumer->source === null) {
                throw ContentSystemException::propertyAliasCollision($this->propertyKey, (string) $contextKey, $this->propertyKey);
            }
        }

        $consumers[$this->propertyKey] = new ContextConsumer(
            type: $candidate->contextType,
            required: false,
            scope: ConsumerScope::Root,
            source: $candidate->source,
        );

        $requirements = $node->dataRequirements;
        if (StorefrontContextMappingCandidateProvider::isApprovedSource($candidate->source)) {
            $existing = $requirements[StorefrontContextLoader::REQUIREMENT_KEY] ?? null;
            if ($existing !== null && ($existing->source !== StorefrontContextLoader::SOURCE || $existing->config->jsonSerialize() !== [])) {
                throw ContentSystemException::mappingRequirementConflict(StorefrontContextLoader::REQUIREMENT_KEY);
            }

            $requirements[StorefrontContextLoader::REQUIREMENT_KEY] = StorefrontContextLoader::requirement();
        }

        $replacement = $node->withContextDefinitions(new ContextDefinitions(
            $definitions->getAllProviders(),
            $consumers,
        ))->withDataRequirements($requirements);
        $this->affected = [$replacement->id];

        return $tree->replace($this->elementId, $replacement);
    }
}
