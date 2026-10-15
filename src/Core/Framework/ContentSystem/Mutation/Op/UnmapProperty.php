<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\StorefrontContext\StorefrontContextLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\StorefrontContextMappingCandidateProvider;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * Removes one property mapping without touching its authored fallback value.
 *
 * @internal
 */
#[Package('framework')]
final class UnmapProperty extends AbstractLayoutMutation
{
    public function __construct(
        private readonly string $elementId,
        private readonly string $propertyKey,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        $node = $tree->find($this->elementId);

        if ($node === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        $definitions = $node->contextDefinitions;
        $consumers = $definitions->getAllConsumers();
        $consumer = $consumers[$this->propertyKey] ?? null;

        if ($consumer === null || $consumer->source === null) {
            return $tree;
        }

        unset($consumers[$this->propertyKey]);

        $requirements = $node->dataRequirements;
        $stillUsesStorefrontContext = false;
        foreach ($consumers as $remainingConsumer) {
            if (
                $remainingConsumer->source !== null
                && StorefrontContextMappingCandidateProvider::isApprovedSource($remainingConsumer->source)
            ) {
                $stillUsesStorefrontContext = true;
                break;
            }
        }

        if (!$stillUsesStorefrontContext) {
            $requirement = $requirements[StorefrontContextLoader::REQUIREMENT_KEY] ?? null;
            if ($requirement?->source === StorefrontContextLoader::SOURCE && $requirement->config->jsonSerialize() === []) {
                unset($requirements[StorefrontContextLoader::REQUIREMENT_KEY]);
            }
        }

        $replacement = $node->withContextDefinitions(new ContextDefinitions(
            $definitions->getAllProviders(),
            $consumers,
        ))->withDataRequirements($requirements);
        $this->affected = [$replacement->id];

        return $tree->replace($this->elementId, $replacement);
    }
}
