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
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DraftLayoutDecoder
{
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
        $tree = [];
        $violations = new ConstraintViolationList();

        foreach ($this->gate($rawLayout) as $entry) {
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
        foreach ($this->decode([$rawElement]) as $element) {
            return $element;
        }

        throw ContentSystemException::invalidLayoutStructure(new ConstraintViolationList());
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

    private function structuralViolation(string $propertyPath, string $message, mixed $invalidValue): ConstraintViolation
    {
        return new ConstraintViolation($message, null, [], null, $propertyPath, $invalidValue);
    }
}
