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
 * Replaces the language map of each key in $values on one element. Every key must name a property the element's
 * type declares translatable, so no other property, no wiring, style or child is reachable through it. What a
 * translated value can still change, and when to grant the privilege, is in `Mutation/docs/translate-element.md`.
 * Each map is judged through {@see PropertyType::admits()} and written as supplied: nothing is normalized, no type
 * default is overlaid, and a map without the anchor entry passes. Every key not in $values carries verbatim, unread.
 *
 * @internal
 */
#[Package('framework')]
final class TranslateElement extends AbstractLayoutMutation
{
    /**
     * @param array<array-key, mixed> $values
     */
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly string $elementId,
        private readonly array $values,
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

        // A client key like "42" arrives as the integer array key 42. Every check and report below reads the cast
        // string, so such a key is judged like any other, an undeclared one reported as not translatable.
        foreach (array_keys($this->values) as $rawKey) {
            $key = (string) $rawKey;

            if (!isset($declared[$key]) || !$declared[$key]->type()->translatable()) {
                throw ContentSystemException::mutationPropertyNotTranslatable($this->elementId, $key);
            }
        }

        $properties = $node->properties();

        foreach ($this->values as $rawKey => $value) {
            $key = (string) $rawKey;
            $candidate = StoredValue::fromDecoded($value);

            if (!$declared[$key]->type()->admits($candidate)) {
                throw ContentSystemException::mutationPropertyValueRejected($this->elementId, $key, get_debug_type($value));
            }

            $properties[$key] = $candidate;
        }

        // A separate pass, so every value rejection reports ahead of every language-key rejection across keys and
        // key iteration order does not decide which of the two reports.
        foreach (array_keys($this->values) as $rawKey) {
            $key = (string) $rawKey;

            $this->rejectNonLanguageKeys($this->elementId, $key, $properties[$key]);
        }

        $this->affected = [$this->elementId];

        return $tree->replace($this->elementId, $node->withProperties($properties));
    }

    public function writePrivilege(): string
    {
        return 'content_layout:translate';
    }
}
