<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * Replaces the value of each key in $values on one element and drops each key in $removeKeys. A value is
 * written as supplied, in its stored shape: a translatable property's value is a language map, a
 * non-translatable primitive's value is the scalar, and a `null` writes a present null. Nothing is
 * normalized and no type default is overlaid, so a dropped key with a type default stays absent until the
 * write-boundary seeder refills it on save.
 *
 * The op-opacity every other operation holds extends here to every key this one is not named for: a key
 * absent from both lists is carried verbatim and never read. Wiring, style, attribution and children are
 * untouched. The one value the operation reads it judges through {@see PropertyType::admits()}, the shared
 * predicate — no match table of its own.
 *
 * @internal
 */
#[Package('framework')]
final class UpdateElementProperties extends AbstractLayoutMutation
{
    /**
     * @param array<string, mixed> $values
     * @param list<string> $removeKeys
     */
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly string $elementId,
        private readonly array $values,
        private readonly array $removeKeys,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        $node = $tree->find($this->elementId);

        if ($node === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        $this->requireRegistered($this->registry, $node->component);

        $declared = $this->registry->get($node->component)->properties();

        foreach ([...array_keys($this->values), ...$this->removeKeys] as $key) {
            if (isset($declared[$key]) && $declared[$key]->type()->isPrimitive()) {
                continue;
            }

            // A JSON member name PHP casts to an integer array key reaches the gate as an int and is
            // undeclared like any other unknown key; the cast keeps that a reported 400 rather than a
            // TypeError on the way to reporting it.
            throw ContentSystemException::mutationPropertyUnknown($this->elementId, (string) $key);
        }

        foreach ($this->removeKeys as $key) {
            if (!\array_key_exists($key, $this->values)) {
                continue;
            }

            throw ContentSystemException::mutationPropertyConflict($this->elementId, $key);
        }

        $properties = $node->properties();

        foreach ($this->values as $key => $value) {
            $wrapped = StoredValue::fromDecoded($value);

            if (!$declared[$key]->type()->admits($wrapped)) {
                throw ContentSystemException::mutationPropertyValueRejected($this->elementId, $key, get_debug_type($value));
            }

            $properties[$key] = $wrapped;
        }

        foreach ($this->removeKeys as $key) {
            unset($properties[$key]);
        }

        $this->affected = [$this->elementId];

        // The replacement subtree is spliced in wholesale, so the target's children stay the instances the
        // input tree held.
        return $tree->replace($this->elementId, $node->withProperties($properties));
    }
}
