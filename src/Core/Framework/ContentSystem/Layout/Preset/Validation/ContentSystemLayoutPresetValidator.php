<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Validation;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\LayoutPresetPayloadCompiler;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentSystemLayoutPresetValidator
{
    /**
     * @param array<string, LayoutPresetSpecificationDto> $presets the presets a `preset:` reference resolves against, including the validated one
     */
    public function validate(string $id, array $presets): ConstraintViolationList
    {
        $violations = new ConstraintViolationList();

        if (!\array_key_exists($id, $presets)) {
            $violations->add($this->unknownReference([], $id));

            return $violations;
        }

        $this->collectUnresolvableReferences($presets[$id]->layout, $presets, [$id], $violations);

        return $violations;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param array<string, LayoutPresetSpecificationDto> $presets
     * @param non-empty-list<string> $path
     */
    private function collectUnresolvableReferences(array $nodes, array $presets, array $path, ConstraintViolationList $violations, int &$size = 0): void
    {
        foreach ($nodes as $node) {
            $reference = $node['preset'] ?? null;

            if (\is_string($reference) && !\array_key_exists($reference, $presets)) {
                $violations->add($this->unknownReference($path, $reference));
            } elseif (\is_string($reference) && \in_array($reference, $path, true)) {
                $violations->add($this->violation(
                    $path,
                    $reference,
                    'Layout preset "{{ id }}" has a circular reference: {{ chain }}',
                    ContentSystemException::LAYOUT_PRESET_CIRCULAR_REFERENCE,
                ));
            } elseif (\is_string($reference) && ++$size === LayoutPresetPayloadCompiler::MAX_EXPANDED_PRESETS) {
                $violations->add($this->violation(
                    $path,
                    $path[0],
                    \sprintf('Layout preset "{{ id }}" reaches the limit of %d nested presets', LayoutPresetPayloadCompiler::MAX_EXPANDED_PRESETS),
                    ContentSystemException::LAYOUT_PRESET_TOO_MANY_REFERENCES,
                ));
            } elseif (\is_string($reference) && $size < LayoutPresetPayloadCompiler::MAX_EXPANDED_PRESETS) {
                $this->collectUnresolvableReferences($presets[$reference]->layout, $presets, [...$path, $reference], $violations, $size);
            }

            foreach (\is_array($node['slots'] ?? null) ? $node['slots'] : [] as $children) {
                if (\is_array($children) && array_is_list($children)) {
                    $this->collectUnresolvableReferences($children, $presets, $path, $violations, $size);
                }
            }
        }
    }

    /**
     * @param list<string> $path
     */
    private function unknownReference(array $path, string $reference): ConstraintViolation
    {
        return $this->violation(
            $path,
            $reference,
            'Layout preset "{{ id }}" references unknown preset "{{ reference }}" via {{ chain }}',
            ContentSystemException::LAYOUT_PRESET_UNKNOWN_REFERENCE,
        );
    }

    /**
     * @param list<string> $path
     */
    private function violation(array $path, string $reference, string $template, string $code): ConstraintViolation
    {
        $id = $path[0] ?? $reference;
        $parameters = ['{{ id }}' => $id, '{{ reference }}' => $reference, '{{ chain }}' => implode(' -> ', [...$path, $reference])];

        return new ConstraintViolation(strtr($template, $parameters), $template, $parameters, null, $id, $reference, null, $code);
    }
}
