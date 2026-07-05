<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Specification;

use Shopware\Core\Framework\Log\Package;

/**
 * Immutable artifact authored in YAML, representing one element type's pre-validated data wiring.
 *
 * @internal
 *
 * @phpstan-type BindingSpecificationSchema = array{
 *     id: string,
 *     type: string,
 *     label: string,
 *     promoted: bool,
 *     resolves: array<string, array{loader: string, config: array<string, mixed>}>,
 *     inputs: array<string, array{default?: mixed, required: bool}>
 * }
 */
#[Package('framework')]
final readonly class BindingSpecification
{
    /**
     * @param array<string, LoaderBinding> $resolves keyed by reference property key
     * @param array<string, BindingInput> $inputs keyed by primitive property key
     *
     * `$promoted` trails `$source` (rather than sitting in first-use order) so that existing positional
     * constructions passing `$source` as the sixth argument keep binding it to source, not promoted.
     */
    public function __construct(
        private string $id,
        private string $type,
        private string $label,
        private array $resolves,
        private array $inputs,
        private string $source = '',
        private bool $promoted = false,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function label(): string
    {
        return $this->label;
    }

    /**
     * @return array<string, LoaderBinding>
     */
    public function resolves(): array
    {
        return $this->resolves;
    }

    /**
     * @return array<string, BindingInput>
     */
    public function inputs(): array
    {
        return $this->inputs;
    }

    public function source(): string
    {
        return $this->source;
    }

    /**
     * The source-qualified id (`source:id`): the registry key, the wire identifier a client passes back as
     * `bindingSpecificationId`, and the unique form of an id two sources may legitimately share bare.
     */
    public function qualifiedId(): string
    {
        return $this->source . ':' . $this->id;
    }

    /**
     * Pure catalog metadata: the author promotes this specification for its type. No server behavior reads
     * it: the scaffold never auto-applies it, the gate never consults it, `bind-element` ignores it.
     */
    public function isPromoted(): bool
    {
        return $this->promoted;
    }

    /**
     * A demoted copy of this specification (`promoted: false`), used by the aggregation backstop to keep exactly
     * one promoted specification per type when more than one survives to the merge.
     */
    public function withoutPromotion(): self
    {
        return new self($this->id, $this->type, $this->label, $this->resolves, $this->inputs, $this->source, false);
    }

    /**
     * @return BindingSpecificationSchema
     */
    public function toSchema(): array
    {
        $resolves = [];
        foreach ($this->resolves as $key => $binding) {
            $resolves[$key] = [
                'loader' => $binding->loader,
                'config' => $binding->config,
            ];
        }

        $inputs = [];
        foreach ($this->inputs as $key => $input) {
            $entry = [];

            if ($input->hasDefault) {
                $entry['default'] = $input->default;
            }

            $entry['required'] = $input->required;
            $inputs[$key] = $entry;
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'promoted' => $this->promoted,
            'resolves' => $resolves,
            'inputs' => $inputs,
        ];
    }
}
