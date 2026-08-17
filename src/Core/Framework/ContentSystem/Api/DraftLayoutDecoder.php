<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTreeStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Single decode path from a draft layout (the unsaved tree as posted to the admin actions) to its element tree,
 * shared by the preview, diagnose, and mutation actions so the structural pre-decode gate cannot drift between
 * them. Every element must be an array with a non-empty string id and component, else a 400 invalidLayoutStructure
 * before any element is decoded. An element config defect (a client defect raised by the codec) is handled per
 * caller: {@see decode()} aggregates it into the same 400 because the tree is unusable for rendering or
 * transformation, while {@see decodeLintable()} collects it as an invalid_config violation and keeps the rest of
 * the tree so it can still be diagnosed. Both decode one root at a time through {@see StoredElementCodec}, so the
 * lenient path can keep the roots that decoded while reporting the ones that did not.
 *
 * The codec is the same one the storage column decodes through, so a draft is admitted exactly as its saved form
 * would be: a malformed structural container — a scalar `slots`, `dataRequirements`, context map, `style` or
 * attribution map — is refused rather than emptied, and nesting past the codec's depth bound is refused too.
 *
 * The strict path ({@see decode()}, and {@see decodeOne()} through it) additionally rejects globally duplicate
 * ids before any operation runs, because a mutation applied to such a tree would silently corrupt or drop
 * content. That rule is read off {@see StoredTree::validate()} rather than restated here. The lenient
 * {@see decodeLintable()} path deliberately does NOT run it: the diagnose route is meant to report a duplicate id
 * as a `duplicate_element_id` violation in its 200 body, not to reject it.
 *
 * Every decoded element's style is canonicalised on the way out, on both paths, through the one
 * {@see StoredTreeStyleNormalizer} the write boundary runs — so a draft previews and diagnoses with the style
 * shape its saved layout will carry. Only style: the write boundary's default seeding and attribution
 * reconciliation stay write-only, so a draft still shows unreconciled attribution and still over-reports an
 * unseeded required default.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DraftLayoutDecoder
{
    public function __construct(
        private readonly StoredElementCodec $elementCodec,
        private readonly StoredTreeStyleNormalizer $styleNormalizer,
        private readonly ViolationConstraintMapper $violationMapper,
    ) {
    }

    /**
     * @param array<int|string, mixed> $rawLayout
     *
     * @return list<StoredElement>
     */
    public function decode(array $rawLayout): array
    {
        $tree = [];
        $violations = new ConstraintViolationList();

        foreach ($this->gate($rawLayout) as $entry) {
            try {
                $tree[] = $this->elementCodec->decode($entry['element']);
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

        $styled = $this->styleNormalizer->normalize(new StoredTree($tree))->roots;

        $this->assertUniqueIds($styled);

        return $styled;
    }

    /**
     * Decodes a single raw element (e.g. the subtree an attach action splices in) through the same structural
     * gate as {@see decode()}: a malformed element is a 400 invalidLayoutStructure rather than a codec 500.
     *
     * @param array<string, mixed> $rawElement
     */
    public function decodeOne(array $rawElement): StoredElement
    {
        // decode() returns exactly one element for one gate-passing input, so [0] is always set; the `?? throw`
        // is a defensive guard kept against a future change to decode()'s contract, not a branch reachable today.
        return $this->decode([$rawElement])[0] ?? throw ContentSystemException::invalidLayoutStructure(new ConstraintViolationList());
    }

    /**
     * @param array<int|string, mixed> $rawLayout
     *
     * @return array{0: list<StoredElement>, 1: list<Violation>}
     */
    public function decodeLintable(array $rawLayout): array
    {
        $tree = [];
        $violations = [];

        foreach ($this->gate($rawLayout) as $entry) {
            try {
                $tree[] = $this->elementCodec->decode($entry['element']);
            } catch (ContentSystemException $exception) {
                if (!ContentSystemException::isClientDefect($exception)) {
                    throw $exception;
                }

                $violations[] = new Violation(ViolationCode::InvalidConfig, $entry['id'], null, $exception->getMessage());
            }
        }

        return [$this->styleNormalizer->normalize(new StoredTree($tree))->roots, $violations];
    }

    /**
     * Structural pre-decode gate: every element must be an array with a non-empty string id and component.
     * Aggregates all malformations into a single 400, before any element is decoded, so a malformed element is
     * a client error instead of a bare codec failure, and so {@see decodeLintable()} has an id to attribute a
     * per-element violation to.
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
     * Strict-path tree validation, run after the whole forest decoded but never on the lenient diagnose decode.
     * Ids that repeat across the forest are the one corruption a structural transform cannot survive — the read
     * primitives match the first, the write primitives rewrite all, so a duplicate silently loses one subtree.
     * The rule itself belongs to the forest, so it is read off {@see StoredTree::validate()}.
     *
     * @param list<StoredElement> $tree
     */
    private function assertUniqueIds(array $tree): void
    {
        $violations = (new StoredTree($tree))->validate();

        if ($violations === []) {
            return;
        }

        throw ContentSystemException::invalidLayoutStructure($this->violationMapper->toConstraintViolationList($violations));
    }

    private function structuralViolation(string $propertyPath, string $message, mixed $invalidValue): ConstraintViolation
    {
        return new ConstraintViolation($message, null, [], null, $propertyPath, $invalidValue);
    }
}
