<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Scaffolding;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingMetadata;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingToken;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * Brings a stored forest into the state the rendering steps require, and hands stored forests back. Its
 * steps are ordered and internal: a caller asks for a prepared tree, never for one of the steps.
 *
 * The order is preparation-event dispatch (owned by the pipeline), authored-token parsing and placeholder
 * resolution, then the virtual-root wrap, the partial prune, and the scaffolding the finishing steps read.
 * Placeholder resolution runs in FULL mode only. The skeleton response
 * carries a tree's structure and its style, never a property value, so resolving into values it discards is
 * work no reader can observe.
 *
 * @internal
 */
#[Package('framework')]
final class StoredTreePreparer
{
    public function __construct(
        private readonly VirtualRootWrapper $virtualRootWrapper,
        private readonly PartialRenderer $partialRenderer,
        private readonly DataLoaderConfigSerializerProvider $configSerializerProvider,
        private readonly DataLoaderProvider $dataLoaderProvider,
        private readonly InlineMappingTokenParser $inlineMappingTokenParser,
    ) {
    }

    /**
     * @param list<StoredElement> $tree
     */
    public function prepare(array $tree, RenderingSpecification $specification, RenderingMode $mode): TreePreparationResult
    {
        $inlineTokens = [];
        $tree = $this->resolveTreePlaceholders($tree, $specification, $mode, $inlineTokens);

        $virtualRootWrapped = $this->virtualRootWrapper->requiresWrapping($specification, $tree);

        if ($virtualRootWrapped) {
            $tree = [$this->virtualRootWrapper->wrap($tree, $specification)];
        }

        $prePruneForest = $tree;

        $extractTargetId = $this->extractTargetId($specification);
        $tree = $this->pruneToTarget($tree, $extractTargetId);

        return new TreePreparationResult(
            $tree,
            $prePruneForest,
            $this->deriveScaffolding($tree, $extractTargetId, $virtualRootWrapped),
            new InlineMappingMetadata($inlineTokens),
        );
    }

    /**
     * @param list<StoredElement> $tree
     * @param array<string, array<string, list<InlineMappingToken>>> $inlineTokens
     *
     * @return list<StoredElement>
     */
    private function resolveTreePlaceholders(array $tree, RenderingSpecification $specification, RenderingMode $mode, array &$inlineTokens): array
    {
        if ($mode !== RenderingMode::FULL) {
            return $tree;
        }

        $prepared = [];
        foreach ($tree as $element) {
            $prepared[] = $this->resolvePlaceholders($element, $specification->placeholderValues, $inlineTokens);
        }

        return $prepared;
    }

    /**
     * The id a partial render extracts, or null when the request addresses the whole layout.
     */
    private function extractTargetId(RenderingSpecification $specification): ?string
    {
        $targetElementId = $specification->targetElementId;

        if ($targetElementId === null || $targetElementId === '') {
            return null;
        }

        return $targetElementId;
    }

    /**
     * Prunes the layout tree to the target element and its dependencies when the `elementId` parameter is present.
     *
     * Pre-hydration tree pruning keeps context-dependent ancestors to preserve data flow; the pipeline's
     * partial extract removes those ancestors after hydration.
     *
     * It runs on the stored forest, before the lowering, so the discarded subtrees never reach the render
     * model at all.
     *
     * @param list<StoredElement> $elements
     *
     * @return list<StoredElement>
     */
    private function pruneToTarget(array $elements, ?string $extractTargetId): array
    {
        if ($extractTargetId === null) {
            return $elements;
        }

        return $this->partialRenderer->pruneToTarget($elements, $extractTargetId);
    }

    /**
     * Records what the finishing steps need to know about the tree these steps produced.
     *
     * `virtualRootSurvivedPrune` is read off the post-prune forest, because the wrap decision alone
     * does not answer whether the virtual root is still there: a partial render addressed at an
     * element that needs no page-level context prunes it away. Element ids are unique across roots,
     * so pruning leaves at most one surviving root whenever a target is set and the first root
     * decides. `$extractTargetId` is passed in already normalised — the prune ran on it.
     *
     * @param list<StoredElement> $elements
     */
    private function deriveScaffolding(array $elements, ?string $extractTargetId, bool $virtualRootWrapped): RenderScaffolding
    {
        $virtualRootSurvivedPrune = $virtualRootWrapped
            && $elements !== []
            && $this->virtualRootWrapper->isVirtualRoot($elements[0]);

        return new RenderScaffolding($virtualRootSurvivedPrune, $extractTargetId);
    }

    /**
     * Rewrites the string values of an element's own property map and recurses into its slot children.
     *
     * A list or map value is handed on untouched, string leaves inside it included: a placeholder is a
     * property of the authored value, and reaching into a container would resolve tokens the authoring
     * surface never offered to resolve.
     *
     * @param array<string, array<string, list<InlineMappingToken>>> $inlineTokens
     */
    private function resolvePlaceholders(StoredElement $element, PlaceholderValues $values, array &$inlineTokens): StoredElement
    {
        $properties = [];
        foreach ($element->properties() as $key => $value) {
            if ($value->isString()) {
                $authored = $value->asString();
                $tokens = $this->inlineMappingTokenParser->parse($authored);
                [$substituted, $adjustedTokens] = $this->substituteAuthoredText($authored, $values, $tokens);
                if ($adjustedTokens !== []) {
                    $inlineTokens[$element->id][(string) $key] = $adjustedTokens;
                }

                $value = $substituted === null ? StoredValue::ofNull() : StoredValue::ofString($substituted);
            }

            $properties[$key] = $value;
        }

        $dataRequirements = [];
        foreach ($element->dataRequirements as $key => $requirement) {
            $dataRequirements[$key] = $this->resolveRequirementPlaceholders($requirement, $values);
        }

        $slots = [];
        foreach ($element->slots as $slotName => $children) {
            $slots[$slotName] = [];
            foreach ($children as $child) {
                $slots[$slotName][] = $this->resolvePlaceholders($child, $values, $inlineTokens);
            }
        }

        return $element->withProperties($properties)->withDataRequirements($dataRequirements)->withSlots($slots);
    }

    private function resolveRequirementPlaceholders(DataRequirement $requirement, PlaceholderValues $values): DataRequirement
    {
        $literalKeys = $this->literalConfigKeys($requirement->source);

        if ($literalKeys === []) {
            return $requirement;
        }

        $config = $this->configSerializerProvider->encode($requirement->source, $requirement->config);
        $substitutedConfig = [];
        foreach ($config as $key => $value) {
            if (!\in_array($key, $literalKeys, true) || !\is_string($value)) {
                continue;
            }

            $newValue = $this->substitute($value, $values);

            if ($newValue === $value) {
                continue;
            }

            $substitutedConfig[$key] = $newValue;
        }

        if ($substitutedConfig === []) {
            return $requirement;
        }

        foreach ($substitutedConfig as $key => $value) {
            if ($value === null) {
                unset($config[$key], $substitutedConfig[$key]);
            }
        }

        $newConfig = $this->configSerializerProvider->decode($requirement->source, array_merge($config, $substitutedConfig));

        return new DataRequirement($requirement->key, $requirement->source, $newConfig);
    }

    /**
     * @return list<string>
     */
    private function literalConfigKeys(string $source): array
    {
        $specification = $this->dataLoaderProvider->get($source)->configSpecification();

        return array_map(
            static fn (ConfigKeySpecification $key): string => $key->name,
            $specification->keysOfKind(ConfigKeyKind::Literal)
        );
    }

    /**
     * A single unresolved placeholder collapses to null; an unresolved placeholder embedded in other text stays
     * verbatim. Substitution does not inspect or recognize mapping syntax.
     */
    private function substitute(string $input, PlaceholderValues $values): ?string
    {
        if (preg_match('/^\{\{\s*(?<key>[^{}:]+?)\s*\}\}$/', $input, $matches) === 1
            && !\array_key_exists($matches['key'], $values->all())
        ) {
            return null;
        }

        return $this->substituteLiteral($input, $values);
    }

    /**
     * Substitutes placeholder values only in literal spans and shifts the authored token offsets to the prepared text.
     * Tokens supplied by placeholder values are never added to this metadata.
     *
     * @param list<InlineMappingToken> $tokens
     *
     * @return array{?string, list<InlineMappingToken>}
     */
    private function substituteAuthoredText(string $input, PlaceholderValues $values, array $tokens): array
    {
        if ($tokens === []) {
            return [$this->substitute($input, $values), []];
        }

        $rewritten = '';
        $cursor = 0;
        $adjustedTokens = [];

        foreach ($tokens as $token) {
            $rewritten .= $this->substituteLiteral(substr($input, $cursor, $token->offset - $cursor), $values);
            $adjustedTokens[] = new InlineMappingToken(
                $token->path,
                $token->match,
                \strlen($rewritten),
                $token->insideMarkup,
            );
            $rewritten .= $token->match;
            $cursor = $token->offset + \strlen($token->match);
        }

        $rewritten .= $this->substituteLiteral(substr($input, $cursor), $values);

        return [$rewritten, $adjustedTokens];
    }

    private function substituteLiteral(string $input, PlaceholderValues $values): string
    {
        foreach ($values->all() as $key => $value) {
            $input = str_replace('{{' . $key . '}}', (string) $value, $input);
        }

        return $input;
    }
}
