<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Single decode path from a draft layout (the unsaved tree as posted to the admin actions) to its element tree,
 * shared by the preview, diagnose, and mutation actions so the structural pre-decode gate cannot drift between
 * them. Every element must be an array with a non-empty string id and component, else a 400 invalidLayoutStructure
 * before any element is decoded. An element config defect (a client defect raised by the field serializer) is
 * handled per caller: {@see decode()} aggregates it into the same 400 because the tree is unusable for rendering
 * or transformation, while {@see decodeLintable()} collects it as an invalid_config violation and keeps the rest
 * of the tree so it can still be diagnosed.
 *
 * The strict path ({@see decode()}, and {@see decodeOne()} through it) additionally rejects a structurally
 * corrupt tree before any operation runs — globally duplicate ids, nesting past {@see MAX_NESTING_DEPTH}, a
 * non-array slot-children container, or a non-array nested child — because a mutation applied to such a tree
 * would silently corrupt or drop content. The
 * lenient {@see decodeLintable()} path deliberately does NOT run that check: the diagnose route is meant to
 * report a duplicate id as a `duplicate_element_id` violation in its 200 body, not to reject it.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DraftLayoutDecoder
{
    private const MAX_NESTING_DEPTH = 50;

    public function __construct(
        private readonly ContentElementFieldSerializer $elementSerializer,
    ) {
    }

    /**
     * @param array<int|string, mixed> $rawLayout
     *
     * @return list<ContentElement>
     */
    public function decode(array $rawLayout): array
    {
        $gated = $this->gate($rawLayout);
        $this->assertWellFormedTree($gated);

        $tree = [];
        $violations = new ConstraintViolationList();

        foreach ($gated as $entry) {
            try {
                $tree[] = $this->elementSerializer->decodeElement($entry['element']);
            } catch (ContentSystemException $exception) {
                if (!ContentSystemException::isClientDefect($exception)) {
                    throw $exception;
                }

                $violations->add($this->structuralViolation('[' . $entry['index'] . ']', $exception->getMessage(), $entry['element']));
            }
        }

        if ($violations->count() > 0) {
            throw ContentSystemException::invalidLayoutStructure($violations);
        }

        return $tree;
    }

    /**
     * Decodes a single raw element (e.g. the subtree an attach action splices in) through the same structural
     * gate as {@see decode()}: a malformed element is a 400 invalidLayoutStructure rather than a serializer 500.
     *
     * @param array<string, mixed> $rawElement
     */
    public function decodeOne(array $rawElement): ContentElement
    {
        return $this->decode([$rawElement])[0] ?? throw ContentSystemException::invalidLayoutStructure(new ConstraintViolationList());
    }

    /**
     * @param array<int|string, mixed> $rawLayout
     *
     * @return array{0: list<ContentElement>, 1: list<Violation>}
     */
    public function decodeLintable(array $rawLayout): array
    {
        $tree = [];
        $violations = [];

        foreach ($this->gate($rawLayout) as $entry) {
            try {
                $tree[] = $this->elementSerializer->decodeElement($entry['element']);
            } catch (ContentSystemException $exception) {
                if (!ContentSystemException::isClientDefect($exception)) {
                    throw $exception;
                }

                $violations[] = new Violation(ViolationCode::InvalidConfig, $entry['id'], null, $exception->getMessage());
            }
        }

        return [$tree, $violations];
    }

    /**
     * Structural pre-decode gate: every element must be an array with a non-empty string id and component.
     * Aggregates all malformations into a single 400, before any element is decoded, so a malformed element is
     * a client error instead of a 500 from the field serializer's id/component guards.
     *
     * @param array<int|string, mixed> $rawLayout
     *
     * @return list<array{index: int|string, id: string, element: array<string, mixed>}>
     */
    private function gate(array $rawLayout): array
    {
        $violations = new ConstraintViolationList();
        $decodable = [];

        foreach ($rawLayout as $index => $element) {
            $path = '[' . $index . ']';

            if (!\is_array($element)) {
                $violations->add($this->structuralViolation($path, 'Layout element must be an array.', $element));

                continue;
            }

            $id = $element['id'] ?? null;
            $component = $element['component'] ?? null;

            if (!\is_string($id) || $id === '') {
                $violations->add($this->structuralViolation($path . '.id', 'Layout element id must be a non-empty string.', $id));
            }

            if (!\is_string($component) || $component === '') {
                $violations->add($this->structuralViolation($path . '.component', 'Layout element component must be a non-empty string.', $component));
            }

            if (\is_string($id) && $id !== '' && \is_string($component) && $component !== '') {
                $decodable[] = ['index' => $index, 'id' => $id, 'element' => $element];
            }
        }

        if ($violations->count() > 0) {
            throw ContentSystemException::invalidLayoutStructure($violations);
        }

        return $decodable;
    }

    /**
     * Strict-path tree validation, run after {@see gate()} on the mutation/preview/attach decode but never on the
     * lenient diagnose decode. Walks the whole tree once and rejects (before any operation runs) the corruptions a
     * structural transform cannot survive: ids that repeat across the tree (the read primitives match the first,
     * the write primitives rewrite all, so a duplicate silently loses one subtree), nesting past
     * {@see MAX_NESTING_DEPTH}, a non-array slot-children container (which would serialize to an empty slot), and
     * a non-array nested child (which the storage serializer would silently drop).
     *
     * @param list<array{index: int|string, id: string, element: array<string, mixed>}> $gated
     */
    private function assertWellFormedTree(array $gated): void
    {
        $violations = new ConstraintViolationList();
        $seenIds = [];

        foreach ($gated as $entry) {
            $this->walkElement($entry['element'], '[' . $entry['index'] . ']', 0, $seenIds, $violations);
        }

        if ($violations->count() > 0) {
            throw ContentSystemException::invalidLayoutStructure($violations);
        }
    }

    /**
     * @param array<array-key, mixed> $element
     * @param array<string, true> $seenIds
     */
    private function walkElement(array $element, string $path, int $depth, array &$seenIds, ConstraintViolationList $violations): void
    {
        $id = $element['id'] ?? null;

        if (\is_string($id) && $id !== '') {
            if (isset($seenIds[$id])) {
                $violations->add($this->structuralViolation($path . '.id', \sprintf('Layout element id "%s" is not unique across the layout.', $id), $id));
            }

            $seenIds[$id] = true;
        }

        $slots = $element['slots'] ?? null;

        if (!\is_array($slots)) {
            return;
        }

        if ($depth + 1 > self::MAX_NESTING_DEPTH) {
            $violations->add($this->structuralViolation($path . '.slots', \sprintf('Layout nesting exceeds the maximum depth of %d.', self::MAX_NESTING_DEPTH), null));

            return;
        }

        foreach ($slots as $slotName => $children) {
            if (!\is_array($children)) {
                $violations->add($this->structuralViolation($path . '.slots.' . $slotName, 'Layout slot must be an array of elements.', $children));

                continue;
            }

            foreach ($children as $childIndex => $child) {
                $childPath = $path . '.slots.' . $slotName . '[' . $childIndex . ']';

                if (!\is_array($child)) {
                    $violations->add($this->structuralViolation($childPath, 'Layout element must be an array.', $child));

                    continue;
                }

                $this->walkElement($child, $childPath, $depth + 1, $seenIds, $violations);
            }
        }
    }

    private function structuralViolation(string $propertyPath, string $message, mixed $invalidValue): ConstraintViolation
    {
        return new ConstraintViolation($message, null, [], null, $propertyPath, $invalidValue);
    }
}
