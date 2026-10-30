<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @internal only for use by the content-system layout presets
 */
#[Package('framework')]
final class LayoutPresetNodesValidator extends ConstraintValidator
{
    private const PRESET = 'preset';

    private const COMPONENT = 'component';

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof LayoutPresetNodes) {
            throw new UnexpectedTypeException($constraint, LayoutPresetNodes::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (!\is_array($value)) {
            return;
        }

        $this->validateNodes($value, '', $constraint);
    }

    /**
     * @param array<int|string, mixed> $nodes
     */
    private function validateNodes(array $nodes, string $path, LayoutPresetNodes $constraint): void
    {
        foreach ($nodes as $index => $node) {
            $nodePath = $path . '[' . $index . ']';

            if (!\is_array($node)) {
                $this->context->buildViolation($constraint->nodeMessage)
                    ->atPath($nodePath)
                    ->addViolation();

                continue;
            }

            $hasComponent = \array_key_exists(self::COMPONENT, $node);
            $hasPreset = \array_key_exists(self::PRESET, $node);

            if ($hasComponent && $hasPreset) {
                $this->context->buildViolation($constraint->ambiguousMessage)
                    ->atPath($nodePath)
                    ->addViolation();

                continue;
            }

            if ($hasPreset) {
                $this->validatePresetReference($node, $nodePath, $constraint);

                continue;
            }

            if (!$hasComponent) {
                $this->context->buildViolation($constraint->missingMessage)
                    ->atPath($nodePath)
                    ->addViolation();

                continue;
            }

            foreach (\is_array($node['slots'] ?? null) ? $node['slots'] : [] as $slot => $children) {
                if (\is_array($children)) {
                    $this->validateNodes($children, $nodePath . '.slots[' . $slot . ']', $constraint);
                }
            }
        }
    }

    /**
     * @param array<int|string, mixed> $node
     */
    private function validatePresetReference(array $node, string $path, LayoutPresetNodes $constraint): void
    {
        $preset = $node[self::PRESET];

        if (!\is_string($preset) || $preset === '') {
            $this->context->buildViolation($constraint->presetTypeMessage)
                ->atPath($path . '.' . self::PRESET)
                ->addViolation();
        }

        $otherProperties = array_diff(array_keys($node), [self::PRESET]);

        if ($otherProperties !== []) {
            $this->context->buildViolation($constraint->presetPropertiesMessage)
                ->setParameter('{{ properties }}', implode(', ', $otherProperties))
                ->atPath($path)
                ->addViolation();
        }
    }
}
