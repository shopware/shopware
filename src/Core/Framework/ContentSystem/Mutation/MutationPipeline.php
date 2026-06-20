<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The shared mutation pipeline: decode the raw draft tree, apply one structural transform, run the
 * diagnostics pass on the whole new tree, and assemble a {@see MutationResult}.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class MutationPipeline
{
    public function __construct(
        private readonly ContentElementFieldSerializer $serializer,
        private readonly LayoutDiagnostics $diagnostics,
    ) {
    }

    /**
     * @param array<int|string, mixed> $rawLayout the current draft tree, same raw shape as the preview action
     * @param list<ProvidedContext>|null $rootContext the bound source's root-ambient context, or null for the well-formedness subset
     */
    public function run(LayoutMutation $mutation, array $rawLayout, ?array $rootContext, ?Context $context = null): MutationResult
    {
        $tree = $this->decode($rawLayout);

        $mutated = $mutation->apply($tree);
        $affected = $mutation->affected();

        $analysis = $this->diagnostics->analyze($mutated, $rootContext, $context);

        return new MutationResult(
            $mutated,
            array_intersect_key($analysis->resolutions, array_flip($affected)),
            $analysis->report,
            $affected,
            $mutation->orphaned(),
            $mutation->droppedWiring(),
        );
    }

    /**
     * Structural pre-decode gate mirroring the preview action: every layout element must be an array with a
     * non-empty string id and component, so a malformed element is a 400 instead of a 500 from the serializer.
     *
     * @param array<int|string, mixed> $rawLayout
     *
     * @return list<ContentElement>
     */
    private function decode(array $rawLayout): array
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
            $valid = true;

            if (!\is_string($id) || $id === '') {
                $violations->add($this->structuralViolation($path . '.id', 'Layout element id must be a non-empty string.', $id));
                $valid = false;
            }

            if (!\is_string($component) || $component === '') {
                $violations->add($this->structuralViolation($path . '.component', 'Layout element component must be a non-empty string.', $component));
                $valid = false;
            }

            if ($valid) {
                $decodable[$index] = $element;
            }
        }

        if ($violations->count() > 0) {
            throw ContentSystemException::invalidLayoutStructure($violations);
        }

        $tree = [];

        foreach ($decodable as $index => $element) {
            try {
                $tree[] = $this->serializer->decodeElement($element);
            } catch (ContentSystemException $exception) {
                if (!ContentSystemException::isClientDefect($exception)) {
                    throw $exception;
                }

                $violations->add($this->structuralViolation('[' . $index . ']', $exception->getMessage(), $element));
            }
        }

        if ($violations->count() > 0) {
            throw ContentSystemException::invalidLayoutStructure($violations);
        }

        return $tree;
    }

    private function structuralViolation(string $propertyPath, string $message, mixed $invalidValue): ConstraintViolation
    {
        return new ConstraintViolation($message, null, [], null, $propertyPath, $invalidValue);
    }
}
